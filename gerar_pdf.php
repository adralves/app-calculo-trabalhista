<?php
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$data             = null;
$row              = null;
$inputs           = [];
$result           = [];
$nomeCapturado    = 'Nao informado';
$whatsappCapturado = 'Nao informado';

if (!empty($_GET['id'])) {
    $uuid = $_GET['id'];

    if (!isset($pdo) || $pdo === null) {
        die('Erro de conexao com o banco de dados.');
    }

    $stmt = $pdo->prepare('SELECT * FROM rescisao_calculos WHERE uuid = ?');
    $stmt->execute([$uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die('Calculo nao encontrado.');
    }

    $inputs           = json_decode($row['dados_input'], true) ?: [];
    $result           = json_decode($row['dados_resultado'], true) ?: [];
    $nomeCapturado    = $row['nome'] ?? $nomeCapturado;
    $whatsappCapturado = $row['whatsapp'] ?? $whatsappCapturado;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['json_data'])) {
    $data = json_decode($_POST['json_data'], true);
    if (!$data) {
        die('Erro ao ler os dados do calculo.');
    }
    $inputs           = $data['inputs'] ?? [];
    $result           = $data['result'] ?? [];
    $nomeCapturado    = $inputs['nome'] ?? $nomeCapturado;
    $whatsappCapturado = $inputs['whatsapp'] ?? $whatsappCapturado;
} else {
    die('Acesso invalido. Por favor, gere o calculo primeiro na pagina inicial.');
}

function formatMoney($val)
{
    return 'R$ ' . number_format((float) $val, 2, ',', '.');
}

function formatMoneyComparativo($val)
{
    $val = (float) $val;

    if ($val < 0) {
        return '-R$ ' . number_format(abs($val), 2, ',', '.');
    }

    return formatMoney($val);
}

function formatDate($dateStr)
{
    if (empty($dateStr)) {
        return '-';
    }
    try {
        $date = new DateTime($dateStr);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

function calcularParcelaSeguroDesemprego($salarioMedio)
{
    $salarioMedio = (float) $salarioMedio;

    if ($salarioMedio <= 2222.17) {
        $parcela = $salarioMedio * 0.8;
    } elseif ($salarioMedio <= 3703.99) {
        $parcela = (($salarioMedio - 2222.17) * 0.5) + 1777.74;
    } else {
        $parcela = 2518.65;
    }

    return max(1621.00, min($parcela, 2518.65));
}

function estimarParcelasSeguroDesemprego($mesesTrabalhados)
{
    if ($mesesTrabalhados >= 24) {
        return 5;
    }

    if ($mesesTrabalhados >= 12) {
        return 4;
    }

    if ($mesesTrabalhados >= 6) {
        return 3;
    }

    return 0;
}

function calcularComparativoRescisao($inputs, $motivoComparado)
{
    $dataAdmissao = new DateTime($inputs['data_admissao'] ?? 'now');
    $dataDemissao = new DateTime($inputs['data_demissao'] ?? 'now');
    $salario = (float) ($inputs['ultimo_salario'] ?? 0);
    $tipoAviso = $inputs['aviso_previo'] ?? 'indenizado';

    $diffTotal = $dataAdmissao->diff($dataDemissao);
    $anosTrabalhados = $diffTotal->y;
    $mesesTrabalhadosTotal = ($anosTrabalhados * 12) + $diffTotal->m;
    if ($diffTotal->d >= 15) {
        $mesesTrabalhadosTotal++;
    }

    $diasTrabalhadosMesDemissao = (int) $dataDemissao->format('d');
    $saldoSalario = ($salario / 30) * $diasTrabalhadosMesDemissao;

    $avisoPrevio = 0;
    if ($motivoComparado === 'rescisao_indireta') {
        $diasAviso = min(30 + (3 * $anosTrabalhados), 90);
        $avisoPrevio = ($salario / 30) * $diasAviso;
    } elseif ($motivoComparado === 'pedido_demissao' && $tipoAviso === 'nao_cumprido') {
        $avisoPrevio = $salario * -1;
    }

    $mesesAnoAtual = (int) $dataDemissao->format('n');
    if ((int) $dataDemissao->format('d') < 15) {
        $mesesAnoAtual--;
    }
    $decimoTerceiro = ($salario / 12) * max(0, $mesesAnoAtual);

    $mesesProp = $diffTotal->m;
    if ($diffTotal->d >= 15) {
        $mesesProp++;
    }
    $feriasProporcionais = ($salario / 12) * max(0, $mesesProp);
    $tercoFerias = $feriasProporcionais / 3;

    $fgtsTotal = 0;
    $multaFgts = 0;
    $seguroDesemprego = 0;
    if ($motivoComparado === 'rescisao_indireta') {
        $fgtsTotal = ($salario * 0.08) * $mesesTrabalhadosTotal;
        $multaFgts = $fgtsTotal * 0.40;
        $seguroDesemprego = calcularParcelaSeguroDesemprego($salario) * estimarParcelasSeguroDesemprego($mesesTrabalhadosTotal);
    }

    $total = $saldoSalario + $avisoPrevio + $decimoTerceiro + $feriasProporcionais + $tercoFerias + $fgtsTotal + $multaFgts + $seguroDesemprego;

    return [
        'saldo_salario' => $saldoSalario,
        'aviso_previo' => $avisoPrevio,
        'decimo_terceiro' => $decimoTerceiro,
        'ferias_proporcionais' => $feriasProporcionais,
        'terco_ferias' => $tercoFerias,
        'fgts_total' => $fgtsTotal,
        'multa_fgts' => $multaFgts,
        'seguro_desemprego' => $seguroDesemprego,
        'total' => $total,
    ];
}

function montarTabelaComparativa($inputs)
{
    $pedido = calcularComparativoRescisao($inputs, 'pedido_demissao');
    $indireta = calcularComparativoRescisao($inputs, 'rescisao_indireta');
    $diferenca = max(0, ($indireta['total'] ?? 0) - ($pedido['total'] ?? 0));

    $linhas = [
        ['Saldo de Sal&aacute;rio', 'saldo_salario'],
        ['Aviso Pr&eacute;vio', 'aviso_previo'],
        ['13&ordm; Proporcional', 'decimo_terceiro'],
        ['F&eacute;rias Proporcionais', 'ferias_proporcionais'],
        ['1/3 de F&eacute;rias', 'terco_ferias'],
        ['FGTS Total (8%)', 'fgts_total'],
        ['Multa FGTS (40%)', 'multa_fgts'],
        ['Seguro Desemprego', 'seguro_desemprego'],
    ];

    $html = '<div class="comparativo-section">';
    $html .= '<h2>Comparativo: Pedido de Demiss&atilde;o vs Rescis&atilde;o Indireta</h2>';
    $html .= '<table class="comparativo-table"><thead><tr>';
    $html .= '<th>Verba Trabalhista</th><th>Pedido de Demiss&atilde;o</th><th>Rescis&atilde;o Indireta</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($linhas as $linha) {
        $html .= '<tr>';
        $html .= '<td>' . $linha[0] . '</td>';
        $html .= '<td class="text-right">' . formatMoneyComparativo($pedido[$linha[1]] ?? 0) . '</td>';
        $html .= '<td class="text-right">' . formatMoneyComparativo($indireta[$linha[1]] ?? 0) . '</td>';
        $html .= '</tr>';
    }

    $html .= '<tr class="comparativo-total">';
    $html .= '<td>TOTAL</td>';
    $html .= '<td class="text-right">' . formatMoneyComparativo($pedido['total'] ?? 0) . '</td>';
    $html .= '<td class="text-right">' . formatMoneyComparativo($indireta['total'] ?? 0) . '</td>';
    $html .= '</tr>';
    $html .= '</tbody></table>';
    $html .= '<div class="comparativo-alert">';
    $html .= '<strong>Voc&ecirc; pode estar perdendo ' . formatMoneyComparativo($diferenca) . '</strong>';
    $html .= '<span>A rescis&atilde;o indireta &eacute; poss&iacute;vel quando a empresa descumpre o contrato de trabalho.</span>';
    $html .= '</div>';
    $html .= '<p class="comparativo-note">Seguro-desemprego estimado com base na tabela vigente desde 11/01/2026, usando o &uacute;ltimo sal&aacute;rio informado como sal&aacute;rio m&eacute;dio.</p>';
    $html .= '</div>';

    return $html;
}

$motivos = [
    'dispensa_sem_justa_causa' => 'Dispensa sem Justa Causa',
    'pedido_demissao'          => 'Pedido de Demissao',
    'dispensa_com_justa_causa' => 'Dispensa com Justa Causa',
    'termino_contrato'         => 'Termino de Contrato',
    'rescisao_indireta'         => 'Rescisao Indireta',
    'acordo'                   => 'Rescisao por Acordo (Reforma Trabalhista)',
];

$motivoKey = $inputs['motivo'] ?? '';
$motivoStr = $motivos[$motivoKey] ?? ($motivoKey !== '' ? $motivoKey : '-');
$detalhes  = is_array($result['detalhes'] ?? null) ? $result['detalhes'] : [];
$resumo    = is_array($result['resumo'] ?? null) ? $result['resumo'] : [];

// Pré-cálculo de todos os valores para uso no HTML
//$msgZap   = "Ola, me chamo " . $nomeCapturado . ". Gostaria de tirar duvidas sobre o meu calculo rescisoio.";

$zapUrl   = "https://wa.me/5585991562067?text=#20 - Olá, me chamo " . $nomeCapturado . ". Gostaria de mais informações sobre o meu calculo rescisório."; //. urlencode($msgZap);

$logoPath   = __DIR__ . '/assets/img/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

$dataAdmissao  = formatDate($inputs['data_admissao'] ?? '');
$dataDemissao  = formatDate($inputs['data_demissao'] ?? '');
$ultimoSalario = formatMoney($inputs['ultimo_salario'] ?? 0);
$avisoPrevio   = isset($inputs['aviso_previo']) ? ucfirst(str_replace('_', ' ', $inputs['aviso_previo'])) : '-';
$feriasVenc    = htmlspecialchars((string)($inputs['ferias_vencidas'] ?? '-'));
$dependentes   = htmlspecialchars((string)($inputs['dependentes'] ?? '-'));

$totalProventos = formatMoney($resumo['total_proventos'] ?? 0);
$totalDescontos = formatMoney($resumo['total_descontos'] ?? 0);
$liquido        = formatMoney($resumo['liquido'] ?? 0);

// Linhas da tabela de detalhamento
$linhasDetalhes = '';
foreach ($detalhes as $item) {
    $desc     = htmlspecialchars($item['descricao'] ?? '-');
    $provento = (($item['tipo'] ?? '') === 'provento') ? formatMoney($item['valor'] ?? 0) : '-';
    $desconto = (($item['tipo'] ?? '') === 'desconto') ? formatMoney($item['valor'] ?? 0) : '-';
    $linhasDetalhes .= "<tr>
        <td>{$desc}</td>
        <td style=\"text-align:right;\">{$provento}</td>
        <td style=\"text-align:right;\">{$desconto}</td>
    </tr>";
}

$logoTag = $logoBase64 ? '<img src="' . $logoBase64 . '" width="100" />' : '';

// ─── HTML DO RELATÓRIO ─────────────────────────────────────────────────────────
$html  = '<!DOCTYPE html>';
$html .= '<html lang="pt-BR"><head><meta charset="UTF-8"><style>';
$html .= '
@page { margin: 30px 40px; }
body { font-family: Arial, sans-serif; color: #333; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; }
.header { width:100%; border-bottom:2px solid #2B3846; padding-bottom:10px; margin-bottom:15px; }
.header-table { width:100%; }
.header-title { text-align:center; vertical-align:middle; }
.header-title h1 { color:#2B3846; font-size:22px; margin:0; }
.section-title { background-color:#2B3846; color:#fff; padding:6px 10px; font-size:14px; font-weight:bold; margin-bottom:8px; margin-top:15px; border-radius:3px; }
table.grid { width:100%; border-collapse:collapse; margin-bottom:15px; font-size:14px; }
table.grid th, table.grid td { border:1px solid #ddd; padding:6px 10px; text-align:left; }
table.grid th { background-color:#f9f9f9; color:#555; }
.text-right { text-align:right; }
.whatsapp-btn { display:block; background-color:#25D366; color:#ffffff; text-decoration:none; text-align:center; padding:12px 20px; font-size:16px; font-weight:bold; border-radius:6px; margin:15px auto; width:220px; }
.whatsapp-btn-wrapper { text-align:center; margin:15px 0; }
.summary-outer { width:100%; text-align:right; margin-top:10px; }
.summary-table { width:350px; border-collapse:collapse; display:inline-table; }
.summary-table td, .summary-table th { padding:8px 10px; border:1px solid #e2e8f0; font-size:14px; text-align:left; }
.summary-proventos { background-color:#F0FDF4; color:#166534; }
.summary-descontos { background-color:#FEF2F2; color:#B91C1C; }
.summary-liquido { background-color:#EFF6FF; color:#1E40AF; font-size:16px; font-weight:bold; }
.comparativo-section { margin-top:22px; page-break-inside:avoid; }
.comparativo-section h2 { color:#2B3846; font-size:20px; margin:0 0 12px; }
.comparativo-alert { background-color:#F0FDF4; border:1px solid #166534; border-radius:8px; color:#166534; margin:15px 0 0; padding:14px 18px; text-align:center; }
.comparativo-alert strong { display:block; font-size:19px; margin-bottom:8px; }
.comparativo-alert span { display:block; color:#3f6849; font-size:13px; }
.comparativo-table { width:100%; border-collapse:collapse; font-size:14px; }
.comparativo-table th { background-color:#2B3846; color:#fff; padding:10px 12px; text-align:left; font-weight:bold; }
.comparativo-table td { padding:9px 12px; color:#555; }
.comparativo-table tbody tr:nth-child(odd) td { background-color:#f9f9f9; }
.comparativo-table tbody tr:nth-child(even) td { background-color:#fff; }
.comparativo-total td { background-color:#2B3846 !important; color:#fff !important; font-size:16px; font-weight:bold; }
.comparativo-note { color:#777; font-size:11px; margin:8px 0 0; }
.footer { margin-top:15px; text-align:center; font-size:11px; color:#999; border-top:1px solid #eee; padding-top:10px; }
';
$html .= '</style></head><body>';

// Cabeçalho
$html .= '<div class="header">
  <table class="header-table"><tr>
    <td style="width:110px;">' . $logoTag . '</td>
    <td class="header-title"><h1>Demonstrativo de Calculo de Rescisao</h1></td>
    <td style="width:110px;"></td>
  </tr></table>
</div>';

// Dados do Solicitante
$html .= '<div class="section-title">DADOS DO SOLICITANTE</div>';
$html .= '<table class="grid"><tr>';
$html .= '<th style="width:25%;">Nome Completo</th><td style="width:25%;">' . htmlspecialchars($nomeCapturado) . '</td>';
$html .= '<th style="width:25%;">WhatsApp</th><td style="width:25%;">' . htmlspecialchars($whatsappCapturado) . '</td>';
$html .= '</tr></table>';

// Dados do Contrato
$html .= '<div class="section-title">DADOS DO CONTRATO DE TRABALHO</div>';
$html .= '<table class="grid">';
$html .= '<tr><th>Motivo da Rescisao</th><td colspan="3">' . htmlspecialchars($motivoStr) . '</td></tr>';
$html .= '<tr><th>Data de Admissao</th><td>' . $dataAdmissao . '</td><th>Data de Demissao</th><td>' . $dataDemissao . '</td></tr>';
$html .= '<tr><th>Ultimo Salario</th><td>' . $ultimoSalario . '</td><th>Aviso Previo</th><td>' . htmlspecialchars($avisoPrevio) . '</td></tr>';
$html .= '<tr><th>Ferias Vencidas (Anos)</th><td>' . $feriasVenc . '</td><th>Dependentes</th><td>' . $dependentes . '</td></tr>';
$html .= '</table>';

// Botão WhatsApp clicável no PDF
$html .= '<div class="whatsapp-btn-wrapper">';
$html .= '<a href="' . htmlspecialchars($zapUrl) . '" class="whatsapp-btn">Falar no WhatsApp</a>';
$html .= '</div>';

// Detalhamento
$html .= '<div class="section-title">DETALHAMENTO DAS VERBAS RESCISORIAS</div>';
$html .= '<table class="grid"><thead><tr>';
$html .= '<th>Descricao / Verba</th>';
$html .= '<th class="text-right" style="width:110px;">Proventos</th>';
$html .= '<th class="text-right" style="width:110px;">Descontos</th>';
$html .= '</tr></thead><tbody>' . $linhasDetalhes . '</tbody></table>';

// Resumo
$html .= '<div class="summary-outer"><table class="summary-table">';
$html .= '<tr class="summary-proventos"><th>Total de Proventos</th><td class="text-right">' . $totalProventos . '</td></tr>';
$html .= '<tr class="summary-descontos"><th>Total de Descontos</th><td class="text-right">' . $totalDescontos . '</td></tr>';
$html .= '<tr class="summary-liquido"><th>Valor Liquido</th><td class="text-right">' . $liquido . '</td></tr>';
$html .= '</table></div>';

if ($motivoKey === 'pedido_demissao') {
    $html .= montarTabelaComparativa($inputs);
}

// Rodapé
$html .= '<div class="footer"><p>Este e um demonstrativo de calculo simplificado e nao substitui os documentos oficiais ou calculos contabeis complexos.</p></div>';
$html .= '</body></html>';

// ─── GERAÇÃO DO PDF ────────────────────────────────────────────────────────────
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');
$options->set('dpi', 96);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nomeArquivo = 'relatorio-rescisao-' . preg_replace('/[^a-z0-9]/i', '-', $nomeCapturado) . '.pdf';
$dompdf->stream($nomeArquivo, ['Attachment' => true]);
