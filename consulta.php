<?php
require_once 'db.php';

$busca = $_GET['busca'] ?? '';
$resultados = [];

if (isset($pdo) && $pdo !== null) {
    // Configurações de Paginação
    $itens_por_pagina = 10;
    $pagina_atual = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
    if ($pagina_atual < 1)
        $pagina_atual = 1;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    if (!empty($busca)) {
        // Busca Unificada (ID, Nome ou WhatsApp)
        $termoLike = "%$busca%";
        
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM rescisao_calculos WHERE id = ? OR nome LIKE ? OR whatsapp LIKE ?");
        $stmtCount->execute([$busca, $termoLike, $termoLike]);
        $total_registros = $stmtCount->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM rescisao_calculos WHERE id = ? OR nome LIKE ? OR whatsapp LIKE ? ORDER BY data_criacao DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $busca, PDO::PARAM_INT);
        $stmt->bindValue(2, $termoLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $termoLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $itens_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Listagem Geral com Contagem
        $total_registros = $pdo->query("SELECT COUNT(*) FROM rescisao_calculos")->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM rescisao_calculos ORDER BY data_criacao DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $itens_por_pagina, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $total_paginas = ceil($total_registros / $itens_por_pagina);
}

function formatMoney($val)
{
    return 'R$ ' . number_format($val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo | Calculadora Trabalhista</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .search-box {
            display: flex;
            gap: 12px;
            margin-bottom: 40px;
        }

        .search-box input {
            flex: 1;
            padding: 14px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 4px rgba(196, 154, 120, 0.1);
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table .header-row th {
            padding: 12px 24px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #ffffff;
            background-color: #2B3846;
            font-weight: 700;
            text-align: left;
            border: none;
        }

        .results-table .header-row th:first-child {
            border-top-left-radius: 16px;
        }

        .results-table .header-row th:last-child {
            border-top-right-radius: 16px;
        }

        .main-row td {
            background-color: #f1f5f9;
            /* Cor que era do hover, agora permanente */
            padding: 16px 24px;
            border: none;
        }

        .main-row td:first-child {
            border-bottom-left-radius: 16px;
        }

        .main-row td:last-child {
            border-bottom-right-radius: 16px;
        }

        .spacer-row td {
            height: 24px;
            background-color: transparent !important;
            border: none !important;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1.05rem;
            display: block;
        }

        .user-date {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .calc-details-row td {
            padding: 0 24px;
            border: none;
        }

        .details-content {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
            border: 1px solid #f1f5f9;
            margin-top: -8px;
        }

        .inner-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .inner-card {
            padding: 24px;
            border-radius: 16px;
            text-align: center;
            transition: transform 0.2s;
        }

        .inner-card:hover {
            transform: translateY(-2px);
        }

        .inner-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #64748b;
            margin-bottom: 12px;
        }

        .inner-value {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0;
        }

        .proventos-card {
            background-color: #F0FDF4;
        }

        .proventos-card .inner-value {
            color: #166534;
        }

        .descontos-card {
            background-color: #FEF2F2;
        }

        .descontos-card .inner-value {
            color: #B91C1C;
        }

        .liquido-card {
            background-color: #EFF6FF;
        }

        .liquido-card .inner-value {
            color: #1E40AF;
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            margin-top: 20px;
        }

        .btn-view:hover {
            background: #f8fafc;
            border-color: var(--gold);
            color: var(--bg-dark);
        }

        /* Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
            margin-bottom: 40px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #2B3846;
            font-size: 14px;
            transition: all 0.2s;
            background: #fff;
        }

        .pagination a:hover {
            background-color: #f1f5f9;
            border-color: var(--gold);
        }

        .pagination .active {
            background-color: var(--gold);
            color: #fff;
            border-color: var(--gold);
            font-weight: 600;
        }

        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
            background: #f9f9f9;
        }
    </style>
</head>

<body class="bg-light">
    <header class="main-header" style="padding: 20px 0;">
        <div class="header-container">
            <a href="https://amaralecastroadvocacia.com/" class="logo">
                <img src="assets/img/logo-principal.png" alt="Logo Principal">
            </a>
            <nav class="main-nav">
                <a href="https://amaralecastroadvocacia.com/">Home</a>
                <a href="index.php">Ir para Calculadora</a>
            </nav>
        </div>
    </header>

    <main class="admin-container">
        <h1 class="gold-text" style="margin-bottom: 20px;">Relatórios e Consultas</h1>

        <form action="" method="GET" class="search-box">
            <input type="text" name="busca" placeholder="Buscar por ID, Nome ou WhatsApp..."
                value="<?= htmlspecialchars($busca) ?>">
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
                <!-- Sem thead global -->
                <tbody>
                    <?php foreach ($resultados as $res):
                        $resData = json_decode($res['dados_resultado'], true);
                        $resumo = $resData['resumo'];
                        $detalhes = $resData['detalhes'];
                        ?>
                        <!-- Cabeçalho Individual por Registro -->
                        <tr class="header-row">
                            <th>ID</th>
                            <th>Data</th>
                            <th>Nome</th>
                            <th>WhatsApp</th>
                            <th class="text-right">Resumo Financeiro</th>
                        </tr>
                        <tr class="main-row">
                            <td><span class="user-name">#<?= $res['id'] ?></span></td>
                            <td><span class="user-date"><?= date('d/m/Y H:i', strtotime($res['data_criacao'])) ?></span></td>
                            <td><span class="user-name"><?= htmlspecialchars($res['nome']) ?></span></td>
                            <td><span class="user-date"><?= htmlspecialchars($res['whatsapp']) ?></span></td>
                            <td class="text-right">
                                <span
                                    style="font-weight: 800; color: #1E40AF; font-size: 1.1rem;"><?= formatMoney($resumo['liquido']) ?></span>
                            </td>
                        </tr>
                        <tr class="calc-details-row">
                            <td colspan="5">
                                <div class="details-content">
                                    <div class="inner-summary">
                                        <div class="inner-card proventos-card">
                                            <span class="inner-label">Proventos</span>
                                            <p class="inner-value"><?= formatMoney($resumo['total_proventos']) ?></p>
                                        </div>
                                        <div class="inner-card descontos-card">
                                            <span class="inner-label">Descontos</span>
                                            <p class="inner-value"><?= formatMoney($resumo['total_descontos']) ?></p>
                                        </div>
                                        <div class="inner-card liquido-card">
                                            <span class="inner-label">Líquido</span>
                                            <p class="inner-value"><?= formatMoney($resumo['liquido']) ?></p>
                                        </div>
                                    </div>

                                    <div style="text-align: right;">
                                        <a href="relatorio.php?id=<?= $res['uuid'] ?>" target="_blank" class="btn-view">Ver
                                            Relatório Completo</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Linha de espaçamento entre os cálculos -->
                        <tr class="spacer-row">
                            <td colspan="5"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <!-- Botão Anterior -->
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>">&laquo; Anterior</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Anterior</span>
                    <?php endif; ?>

                    <!-- Páginas Numéricas -->
                    <?php
                    $max_links = 5;
                    $start = max(1, $pagina_atual - floor($max_links / 2));
                    $end = min($total_paginas, $start + $max_links - 1);
                    if ($end - $start + 1 < $max_links) {
                        $start = max(1, $end - $max_links + 1);
                    }

                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>"
                            class="<?= ($i === $pagina_atual) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Botão Próximo -->
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>">Próximo &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Próximo &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="text-align: center; color: #64748b; font-size: 14px; margin-bottom: 40px;">
                Exibindo <?= count($resultados) ?> de <?= $total_registros ?> registros encontrados.
            </div>
        <?php endif; ?>
    </main>
</body>

</html>