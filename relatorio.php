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

        @media print {
            .no-print {
                display: none;
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
            <button onclick="window.print()"
                style="padding: 10px 20px; background: #6ED886; color: #fff; border: none; cursor: pointer; border-radius: 5px; font-weight: bold;">Imprimir
                / Salvar PDF</button>
        </div>

        <div class="footer">
            <p>Este e um demonstrativo de calculo simplificado e nao substitui os documentos oficiais ou calculos
                contabeis complexos.</p>
        </div>
    </div>
</body>

</html>