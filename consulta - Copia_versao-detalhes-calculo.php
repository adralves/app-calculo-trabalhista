<?php
require_once 'db.php';

$busca = $_GET['busca'] ?? '';
$resultados = [];

if (isset($pdo) && $pdo !== null) {
    if (!empty($busca)) {
        // Busca por ID (se for numérico), Nome ou WhatsApp
        if (is_numeric($busca)) {
            $stmt = $pdo->prepare("SELECT * FROM rescisao_calculos WHERE id = ? ORDER BY data_criacao DESC");
            $stmt->execute([$busca]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM rescisao_calculos WHERE nome LIKE ? OR whatsapp LIKE ? ORDER BY data_criacao DESC");
            $stmt->execute(["%$busca%", "%$busca%"]);
        }
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Mostrar os últimos 20 por padrão
        $stmt = $pdo->query("SELECT * FROM rescisao_calculos ORDER BY data_criacao DESC LIMIT 20");
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function formatMoney($val) {
    return 'R$ ' . number_format($val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Cálculos | Administrativo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        .search-box input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }
        .results-table th, .results-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .results-table th {
            background: #2B3846;
            color: #fff;
            font-weight: 600;
        }
        .calc-details-row {
            background-color: #f8fafc;
        }
        .details-content {
            padding: 20px;
            border-left: 4px solid var(--gold);
        }
        .btn-view {
            background: #C49A78;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: opacity 0.2s;
            display: inline-block;
        }
        .btn-view:hover {
            opacity: 0.8;
        }
        .inner-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .inner-card {
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .inner-card h4 { font-size: 0.75rem; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .inner-card p { font-size: 1.1rem; font-weight: 700; }
        .inner-card.liquido-card { background: #2B3846; color: white; }
        .inner-card.liquido-card p { color: var(--gold); }

        .details-list-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            background: white;
        }
        .details-list-table th, .details-list-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .positive { color: #166534; }
        .negative { color: #991b1b; }
    </style>
</head>
<body class="bg-light">
    <header class="main-header" style="padding: 20px 0;">
        <div class="header-container">
            <div class="logo">
                <span>Painel <strong class="gold-text">Administrativo</strong></span>
            </div>
            <nav class="main-nav">
                <a href="index.php">Ir para Calculadora</a>
            </nav>
        </div>
    </header>

    <main class="admin-container">
        <h1 class="gold-text" style="margin-bottom: 20px;">Relatórios e Consultas</h1>
        
        <form action="" method="GET" class="search-box">
            <input type="text" name="busca" placeholder="Buscar por ID (#), Nome ou WhatsApp..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn btn-primary" style="padding: 10px 25px; width: auto;">Pesquisar</button>
        </form>

        <?php if (!isset($pdo) || $pdo === null): ?>
            <div class="card" style="border-left: 5px solid #ef4444;">
                <p><strong>Atenção:</strong> O banco de dados não está configurado.</p>
            </div>
        <?php elseif (empty($resultados)): ?>
            <div class="card" style="text-align: center; padding: 40px; color: #666;">
                <p>Nenhum cálculo encontrado para sua busca.</p>
            </div>
        <?php else: ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Nome</th>
                        <th>WhatsApp</th>
                        <th class="text-right">Resumo Financeiro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $res): 
                        $resData = json_decode($res['dados_resultado'], true);
                        $resumo = $resData['resumo'];
                        $detalhes = $resData['detalhes'];
                    ?>
                        <tr>
                            <td><strong>#<?= $res['id'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($res['data_criacao'])) ?></td>
                            <td><?= htmlspecialchars($res['nome']) ?></td>
                            <td><?= htmlspecialchars($res['whatsapp']) ?></td>
                            <td class="text-right">
                                <span class="gold-text" style="font-weight: 700;"><?= formatMoney($resumo['liquido']) ?></span>
                            </td>
                        </tr>
                        <tr class="calc-details-row">
                            <td colspan="5">
                                <div class="details-content">
                                    <div class="inner-summary">
                                        <div class="inner-card">
                                            <h4>Proventos</h4>
                                            <p class="positive"><?= formatMoney($resumo['total_proventos']) ?></p>
                                        </div>
                                        <div class="inner-card">
                                            <h4>Descontos</h4>
                                            <p class="negative"><?= formatMoney($resumo['total_descontos']) ?></p>
                                        </div>
                                        <div class="inner-card liquido-card">
                                            <h4>Valor Líquido</h4>
                                            <p><?= formatMoney($resumo['liquido']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;"><strong>Detalhamento das Verbas:</strong></div>
                                    <table class="details-list-table">
                                        <thead>
                                            <tr>
                                                <th>Descrição</th>
                                                <th class="text-right">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detalhes as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['descricao']) ?></td>
                                                    <td class="text-right <?= $item['tipo'] === 'provento' ? 'positive' : 'negative' ?>">
                                                        <?= ($item['tipo'] === 'desconto' ? '-' : '') . formatMoney($item['valor']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div style="margin-top: 15px; text-align: right;">
                                        <a href="relatorio.php?id=<?= $res['uuid'] ?>" target="_blank" class="btn-view">Ver Relatório Completo (PDF)</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
