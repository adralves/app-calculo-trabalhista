<?php
header('Content-Type: application/json');
require_once '../db.php';

// Recebe dados da requisição JSON do Fetch
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos.']);
    exit;
}

// Extração de Variáveis
$motivo = $data['motivo'] ?? '';
$dataAdmissao = new DateTime($data['data_admissao']);
$dataDemissao = new DateTime($data['data_demissao']);
$salario = floatval($data['ultimo_salario']);
$tipoAviso = $data['aviso_previo'] ?? 'indenizado';
$feriasVencidasAnos = intval($data['ferias_vencidas'] ?? 0);
$dependentes = intval($data['dependentes'] ?? 0);
$nome = $data['nome'] ?? 'Anônimo';
$whatsapp = preg_replace('/\D+/', '', $data['whatsapp'] ?? '0000000000');
$data['whatsapp'] = $whatsapp;

$diffTotal = $dataAdmissao->diff($dataDemissao);
$anosTrabalhados = $diffTotal->y;
$mesesTrabalhadosTotal = ($anosTrabalhados * 12) + $diffTotal->m;

// ---- INÍCIO DO CÁLCULO ----

$proventos = [];
$descontos = [];

// 1. Saldo de Salário
$diasTrabalhadosMesDemissao = intval($dataDemissao->format('d'));
$saldoSalario = ($salario / 30) * $diasTrabalhadosMesDemissao;
$proventos[] = ['descricao' => 'Saldo de Salário (' . $diasTrabalhadosMesDemissao . ' dias)', 'valor' => $saldoSalario, 'tipo' => 'provento'];

// 2. Aviso Prévio (Genérico/Simplificado)
$valorAvisoPrevio = 0;
if ($motivo !== 'pedido_demissao' && $motivo !== 'dispensa_com_justa_causa' && $tipoAviso === 'indenizado') {
    $diasAviso = 30 + (3 * $anosTrabalhados);
    $diasAviso = min($diasAviso, 90); // Limite de 90 dias
    $valorAvisoPrevio = ($salario / 30) * $diasAviso;
    $proventos[] = ['descricao' => 'Aviso Prévio Indenizado (' . $diasAviso . ' dias)', 'valor' => $valorAvisoPrevio, 'tipo' => 'provento'];
} elseif ($motivo === 'pedido_demissao' && $tipoAviso === 'nao_cumprido') {
    $descontos[] = ['descricao' => 'Desconto Aviso Prévio Não Cumprido', 'valor' => $salario, 'tipo' => 'desconto'];
}

// 3. 13º Salário Proporcional
function mesesAvos($dataIni, $dataFim) {
    $diff = $dataIni->diff($dataFim);
    $meses = $diff->m;
    if ($diff->d >= 15) {
        $meses++;
    }
    return $meses;
}

$mesesAnoAtual = $dataDemissao->format('n');
if ($dataDemissao->format('d') < 15) {
    $mesesAnoAtual--;
}
$decimoTerceiro = 0;
if ($motivo !== 'dispensa_com_justa_causa') {
    $decimoTerceiro = ($salario / 12) * $mesesAnoAtual;
    if ($decimoTerceiro > 0) {
        $proventos[] = ['descricao' => '13º Salário Proporcional (' . $mesesAnoAtual . '/12)', 'valor' => $decimoTerceiro, 'tipo' => 'provento'];
    }
}

// 4. Férias Vencidas + 1/3
if ($feriasVencidasAnos > 0 && $motivo !== 'dispensa_com_justa_causa') {
    $valorFeriasVenc = $salario * $feriasVencidasAnos;
    $tercoFeriasVenc = $valorFeriasVenc / 3;
    $proventos[] = ['descricao' => 'Férias Vencidas', 'valor' => $valorFeriasVenc, 'tipo' => 'provento'];
    $proventos[] = ['descricao' => '1/3 Férias Vencidas', 'valor' => $tercoFeriasVenc, 'tipo' => 'provento'];
}

// 5. Férias Proporcionais + 1/3
$mesesAquisitivo = mesesAvos(new DateTime($dataAdmissao->format('Y') . '-' . $dataAdmissao->format('m') . '-' . $dataAdmissao->format('d')), $dataDemissao); 
// Simplificação: meses totais modulo 12 + 1 se dia >= 15. Usaremos o diffTotal mod 12 para simplificar o aquisitivo
$mesesProp = $diffTotal->m;
if ($diffTotal->d >= 15) {
    $mesesProp++;
}
if ($mesesProp > 0 && $motivo !== 'dispensa_com_justa_causa') {
    $valorFeriasProp = ($salario / 12) * $mesesProp;
    $tercoFeriasProp = $valorFeriasProp / 3;
    $proventos[] = ['descricao' => 'Férias Proporcionais (' . $mesesProp . '/12)', 'valor' => $valorFeriasProp, 'tipo' => 'provento'];
    $proventos[] = ['descricao' => '1/3 Férias Proporcionais', 'valor' => $tercoFeriasProp, 'tipo' => 'provento'];
}

// 6. Multa FGTS (Simplificado - Base de 8% mensal ao longo dos anos)
$multaFGTS = 0;
if ($motivo === 'dispensa_sem_justa_causa' || $motivo === 'rescisao_indireta') {
    // Estimativa do saldo do FGTS (8% do salario x meses trabalhados)
    $saldoFGTS = ($salario * 0.08) * $mesesTrabalhadosTotal;
    $multaFGTS = $saldoFGTS * 0.40; // Multa 40%
    $proventos[] = ['descricao' => 'Multa 40% FGTS (Valor Estimado)', 'valor' => $multaFGTS, 'tipo' => 'provento'];
} elseif ($motivo === 'acordo') {
    $saldoFGTS = ($salario * 0.08) * $mesesTrabalhadosTotal;
    $multaFGTS = $saldoFGTS * 0.20; // Multa 20%
    $proventos[] = ['descricao' => 'Multa 20% FGTS (Valor Estimado)', 'valor' => $multaFGTS, 'tipo' => 'provento'];
}

// 7. Desconto de INSS e IRRF Simplificado (Sobre Saldo de Salário e 13º)
// Tabela fictícia 2024 pra garantir logica
$baseInss = $saldoSalario + $decimoTerceiro; // Simplificação extrema
$inss = 0;
if ($baseInss <= 1412) $inss = $baseInss * 0.075;
elseif ($baseInss <= 2666.68) $inss = ($baseInss * 0.09) - 21.18;
elseif ($baseInss <= 4000.03) $inss = ($baseInss * 0.12) - 101.18;
else $inss = ($baseInss * 0.14) - 181.18;

if ($inss > 908.85) $inss = 908.85; // Teto

if ($inss > 0) {
    $descontos[] = ['descricao' => 'INSS (Estimativa Simplificada)', 'valor' => $inss, 'tipo' => 'desconto'];
}

// IRRF (Simplificado)
$baseIrrf = $baseInss - $inss - ($dependentes * 189.59);
$irrf = 0;
if ($baseIrrf > 2259.20 && $baseIrrf <= 2826.65) $irrf = ($baseIrrf * 0.075) - 169.44;
elseif ($baseIrrf > 2826.65 && $baseIrrf <= 3751.05) $irrf = ($baseIrrf * 0.15) - 381.44;
elseif ($baseIrrf > 3751.05 && $baseIrrf <= 4664.68) $irrf = ($baseIrrf * 0.225) - 662.77;
elseif ($baseIrrf > 4664.68) $irrf = ($baseIrrf * 0.275) - 896.00;

if ($irrf > 0) {
    $descontos[] = ['descricao' => 'IRRF (Estimativa Simplificada)', 'valor' => $irrf, 'tipo' => 'desconto'];
}

// Consolidando os Valores
$totalProventos = 0;
foreach ($proventos as $p) $totalProventos += $p['valor'];

$totalDescontos = 0;
foreach ($descontos as $d) $totalDescontos += $d['valor'];

$liquido = max(0, $totalProventos - $totalDescontos);

// Monta lista final concatenando proventos e descontos
$detalhes = array_merge($proventos, $descontos);

$resultado = [
    'data_calculo' => date('Y-m-d H:i:s'),
    'resumo' => [
        'total_proventos' => $totalProventos,
        'total_descontos' => $totalDescontos,
        'liquido' => $liquido
    ],
    'detalhes' => $detalhes
];

// Persistência no Banco de Dados
$uuid = null;
$dbId = null;
if (isset($pdo) && $pdo !== null) {
    try {
        $uuid = generate_uuid();
        $stmt = $pdo->prepare("INSERT INTO rescisao_calculos (uuid, nome, whatsapp, dados_input, dados_resultado) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $uuid,
            $nome,
            $whatsapp,
            json_encode($data),
            json_encode($resultado)
        ]);
        $dbId = $pdo->lastInsertId();
    } catch (Exception $e) {
        $uuid = null; // Falhou ao salvar, mas continuamos para não travar o app
        $dbId = null;
        error_log("Erro ao salvar cálculo: " . $e->getMessage());
    }
}

$resultado['uuid'] = $uuid;
$resultado['db_id'] = $dbId;

echo json_encode($resultado);
