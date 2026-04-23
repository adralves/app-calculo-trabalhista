<?php
require_once 'db.php';

$data = null;
$row = null;
$inputs = [];
$result = [];
$nomeCapturado = 'Nao informado';
$whatsappCapturado = 'Nao informado';

if (!empty($_GET['id'])) {
    $uuid = $_GET['id'];

    if (!isset($pdo) || $pdo === null) {
        die('Erro de conexao com o banco de dados. Verifique a configuracao.');
    }

    $stmt = $pdo->prepare('SELECT * FROM rescisao_calculos WHERE uuid = ?');
    $stmt->execute([$uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die('Calculo nao encontrado no sistema.');
    }

    $inputs = json_decode($row['dados_input'], true) ?: [];
    $result = json_decode($row['dados_resultado'], true) ?: [];
    $nomeCapturado = $row['nome'] ?? $nomeCapturado;
    $whatsappCapturado = $row['whatsapp'] ?? $whatsappCapturado;
    $data = ['inputs' => $inputs, 'result' => $result];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['json_data'])) {
    $data = json_decode($_POST['json_data'], true);

    if (!$data) {
        die('Erro ao ler os dados do calculo.');
    }

    $inputs = $data['inputs'] ?? [];
    $result = $data['result'] ?? [];
    $nomeCapturado = $inputs['nome'] ?? $nomeCapturado;
    $whatsappCapturado = $inputs['whatsapp'] ?? $whatsappCapturado;
} else {
    die('Acesso invalido. Por favor, gere o calculo primeiro na pagina inicial.');
}

function formatMoney($val)
{
    return 'R$ ' . number_format((float) $val, 2, ',', '.');
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

$motivos = [
    'dispensa_sem_justa_causa' => 'Dispensa sem Justa Causa',
    'pedido_demissao' => 'Pedido de Demissão',
    'dispensa_com_justa_causa' => 'Dispensa com Justa Causa',
    'termino_contrato' => 'Termino de Contrato',
    'acordo' => 'Rescisão por Acordo (Reforma Trabalhista)',
];

$motivoKey = $inputs['motivo'] ?? '';
$motivoStr = $motivos[$motivoKey] ?? ($motivoKey !== '' ? $motivoKey : '-');
$detalhes = is_array($result['detalhes'] ?? null) ? $result['detalhes'] : [];
$resumo = is_array($result['resumo'] ?? null) ? $result['resumo'] : [];

$mensagemZap = "Olá, me chamo " . $nomeCapturado . ". Gostaria de tirar dúvidas sobre o meu cálculo rescisório.";
$zapUrl = "https://wa.me/5585991562067?text=" . urlencode($mensagemZap);

// URL para geração de PDF via dompdf
$pdfUrl = !empty($_GET['id']) ? 'gerar_pdf.php?id=' . urlencode($_GET['id']) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório de Rescisão Trabalhista</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            /* Fundo cinza suave para tela */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .container {
            max-width: 850px;
            margin: 30px auto;
            background-color: #fff;
            padding: 50px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .header {
            display: grid;
            grid-template-columns: 120px 1fr 120px;
            align-items: center;
            border-bottom: 2px solid #2B3846;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .logo-report {
            max-width: 110px;
            height: auto;
            grid-column: 1;
        }

        .header-titles {
            grid-column: 2;
            text-align: center;
        }

        .header h1 {
            color: #2B3846;
            margin: 0;
            font-size: 22px;
        }

        .header p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }

        .section-title {
            background-color: #2B3846;
            color: #fff;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            border-radius: 3px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 13px;
        }

        table.grid th,
        table.grid td {
            border: 1px solid #ddd;
            padding: 6px 10px;
            text-align: left;
        }

        table.grid th {
            background-color: #f9f9f9;
            color: #555;
        }

        .text-right {
            text-align: right !important;
        }

        .summary-box {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .summary-table {
            width: 300px;
            border-collapse: collapse;
        }

        .summary-table td,
        .summary-table th {
            padding: 8px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .summary-table th {
            background-color: transparent;
            text-align: left;
        }

        .summary-proventos {
            background-color: #F0FDF4 !important;
            color: #166534 !important;
        }

        .summary-descontos {
            background-color: #FEF2F2 !important;
            color: #B91C1C !important;
        }

        .summary-liquido {
            background-color: #EFF6FF !important;
            color: #1E40AF !important;
            font-size: 16px !important;
            font-weight: bold !important;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 10px;
            color: #999;
        }

        .whatsapp-cta {
            background-color: #25D366;
            color: white !important;
            padding: 1rem;
            font-size: 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 5px;
            font-weight: bold;
            transition: transform 0.2s, background-color 0.2s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            margin-top: 15px;
            margin-bottom: 20px;
            width: fit-content;
        }
        .whatsapp-cta:hover {
            background-color: #128C7E;
            transform: translateY(-3px);
        }

        @media print {
            .no-print {
                display: none;
            }

            .whatsapp-cta {
                display: flex !important;
                justify-content: center;
                align-items: center;
                background-color: #25D366 !important;
                color: white !important;
                border: none !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                text-decoration: none !important;
            }

            .whatsapp-cta svg {
                vertical-align: middle;
                margin-right: 8px;
                display: inline-block !important;
                fill: white !important;
            }

            body {
                font-size: 11.5pt;
                background-color: #fff;
            }

            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                border-radius: 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">


        <div class="header">
            <img src="assets/img/logo.png" alt="Logo" class="logo-report">
            <div class="header-titles">
                <h1>Demonstrativo de Calculo de Rescisão</h1>
            </div>
            <div style="width: 120px;"></div> <!-- Espaçador para manter o título centralizado -->
        </div>

        <div class="section-title">DADOS DO SOLICITANTE</div>
        <table class="grid">
            <tr>
                <th style="width: 25%;">Nome Completo</th>
                <td style="width: 25%;"><?= htmlspecialchars($nomeCapturado) ?></td>
                <th style="width: 25%;">WhatsApp</th>
                <td style="width: 25%;"><?= htmlspecialchars($whatsappCapturado) ?></td>
            </tr>
        </table>

        <div class="section-title">DADOS DO CONTRATO DE TRABALHO</div>
        <table class="grid">
            <tr>
                <th>Motivo da Rescisão</th>
                <td colspan="3"><?= htmlspecialchars($motivoStr) ?></td>
            </tr>
            <tr>
                <th>Data de Admissão</th>
                <td><?= formatDate($inputs['data_admissao'] ?? null) ?></td>
                <th>Data de Demissão</th>
                <td><?= formatDate($inputs['data_demissao'] ?? null) ?></td>
            </tr>
            <tr>
                <th>Último Salario</th>
                <td><?= formatMoney($inputs['ultimo_salario'] ?? 0) ?></td>
                <th>Aviso Previo</th>
                <td><?= htmlspecialchars(isset($inputs['aviso_previo']) ? ucfirst(str_replace('_', ' ', $inputs['aviso_previo'])) : '-') ?>
                </td>
            </tr>
            <tr>
                <th>Férias Vencidas (Anos)</th>
                <td><?= htmlspecialchars((string) ($inputs['ferias_vencidas'] ?? '-')) ?></td>
                <th>Dependentes</th>
                <td><?= htmlspecialchars((string) ($inputs['dependentes'] ?? '-')) ?></td>
            </tr>
        </table>

        <!-- Botão do WhatsApp Replicado -->
     <!--    <div style="display: flex; justify-content: center;">
            <a href="<?= htmlspecialchars($zapUrl) ?>" target="_blank" class="whatsapp-cta">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.394 0 12.03c0 2.12.551 4.189 1.597 6.027L0 24l6.135-1.61a11.811 11.811 0 005.908 1.603h.005c6.637 0 12.033-5.395 12.036-12.033a11.976 11.976 0 00-3.532-8.513z"/></svg>
                Falar no WhatsApp
            </a>
        </div> -->

        <div class="section-title">DETALHAMENTO DAS VERBAS RESCISÓRIAS</div>
        <table class="grid">
            <thead>
                <tr>
                    <th>Descrição / Verba</th>
                    <th class="text-right" style="width: 120px;">Proventos</th>
                    <th class="text-right" style="width: 120px;">Descontos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalhes as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['descricao'] ?? '-') ?></td>
                        <td class="text-right positive">
                            <?= (($item['tipo'] ?? '') === 'provento') ? formatMoney($item['valor'] ?? 0) : '-' ?>
                        </td>
                        <td class="text-right negative">
                            <?= (($item['tipo'] ?? '') === 'desconto') ? formatMoney($item['valor'] ?? 0) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <table class="summary-table">
                <tr class="summary-proventos">
                    <th>Total de Proventos</th>
                    <td class="text-right"><?= formatMoney($resumo['total_proventos'] ?? 0) ?></td>
                </tr>
                <tr class="summary-descontos">
                    <th>Total de Descontos</th>
                    <td class="text-right"><?= formatMoney($resumo['total_descontos'] ?? 0) ?></td>
                </tr>
                <tr class="summary-liquido">
                    <th>Valor Líquido</th>
                    <td class="text-right"><?= formatMoney($resumo['liquido'] ?? 0) ?></td>
                </tr>
            </table>
        </div>

        <!-- Botões de Ação -->
        <div class="no-print"
            style="margin-top: 20px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
            <button
                onclick="if(document.referrer) { window.location.href = document.referrer; } else { window.close(); }"
                style="padding: 10px 20px; background: #64748b; color: #fff; border: none; cursor: pointer; border-radius: 5px; font-weight: bold;">Voltar</button>
            <?php if ($pdfUrl): ?>
            <a href="<?= htmlspecialchars($pdfUrl) ?>"
                style="padding: 10px 20px; background: #6ED886; color: #fff; border: none; cursor: pointer; border-radius: 5px; font-weight: bold; text-decoration: none; display: inline-block;">
                &#128462; Salvar PDF</a>
            <?php else: ?>
            <button onclick="window.print()"
                style="padding: 10px 20px; background: #6ED886; color: #fff; border: none; cursor: pointer; border-radius: 5px; font-weight: bold;">Imprimir / Salvar PDF</button>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Este e um demonstrativo de calculo simplificado e nao substitui os documentos oficiais ou calculos
                contabeis complexos.</p>
        </div>
    </div>
</body>

</html>