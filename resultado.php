<?php
require_once 'db.php';

$uuid = $_GET['id'] ?? '';
$nome = $_GET['nome'] ?? 'Cliente';
$id_calc = $_GET['id_calc'] ?? '0';
$valor = $_GET['valor'] ?? '0';

if (empty($uuid)) {
    header('Location: index.php');
    exit;
}

function formatMoney($val) {
    return 'R$ ' . number_format($val, 2, ',', '.');
}

// Mensagem para o WhatsApp
//$mensagemZap = "Olá, me chamo " . $nome . ". Meu número de cálculo é " . $id_calc . ". Gostaria de receber o relatório completo.";
$zapUrl = "https://wa.me/5585991562067?text=Olá! Gostaria de receber o relatório completo."; //. urlencode($mensagemZap);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado Pronto | Calculadora Trabalhista</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .success-page {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-card {
            max-width: 500px;
            width: 100%;
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .check-icon {
            width: 80px;
            height: 80px;
            background: var(--green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(110, 216, 134, 0.3);
        }
        .valor-box {
            background: var(--bg-dark);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin: 2rem 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .valor-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        .valor-box h3 {
            color: #cbd5e1;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }
        .valor-box p {
            font-size: 3rem;
            font-weight: 700;
            color: var(--gold);
        }
        .whatsapp-cta {
            background-color: #25D366;
            color: white !important;
            padding: 1.2rem;
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 8px;
            font-weight: 700;
            transition: transform 0.2s, background-color 0.2s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        .whatsapp-cta:hover {
            background-color: #128C7E;
            transform: translateY(-3px);
        }
        .id-badge {
            display: inline-block;
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 10px;
        }
        .disclaimer-box {
            margin-top: 20px;
            padding: 12px 15px;
            border: 1px solid #facc15;
            border-radius: 10px;
            font-size: 0.8rem;
            color: #94a3b8;
            background-color: rgba(250, 204, 21, 0.05);
            text-align: justify;
            line-height: 1.5;
        }

        /* Ajustes para Celular (Mobile) */
        @media (max-width: 480px) {
            .valor-box p {
                font-size: 2rem; /* Valor menor no celular */
            }
            .valor-box h3 {
                font-size: 1rem;
            }
            .valor-box {
                padding: 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="main-header" style="padding-bottom: 1rem;">
        <div class="header-container">
            <a href="https://amaralecastroadvocacia.com/" class="logo">
                <img src="assets/img/logo-principal.png" alt="Logo Principal">
            </a>
            <nav class="main-nav">
                <a href="https://amaralecastroadvocacia.com/">Home</a>
                <a href="index.php">Novo Cálculo</a>
            </nav>
        </div>
    </header>

    <main class="success-page">
        <div class="card success-card">
            <div class="check-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
            <h1 class="gold-text">Seu resultado está pronto!</h1>
            <p>Olá <strong><?= htmlspecialchars($nome) ?></strong>, calculamos seus direitos com base nas informações fornecidas.</p>
            
            <a href="<?= $zapUrl ?>" target="_blank" class="whatsapp-cta" style="margin-top: 20px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.394 0 12.03c0 2.12.551 4.189 1.597 6.027L0 24l6.135-1.61a11.811 11.811 0 005.908 1.603h.005c6.637 0 12.033-5.395 12.036-12.033a11.976 11.976 0 00-3.532-8.513z"/></svg>
                Receber Relatório no WhatsApp
            </a>
            
            <div class="valor-box">
                <h3>Valor Líquido Estimado</h3>
                <p><?= formatMoney($valor) ?></p>
            </div>
            
            <div class="disclaimer-box">
                ⚠️ Este é um <strong>cálculo estimado</strong> com base nas informações fornecidas. Os valores reais podem variar conforme convenções coletivas, acordos e outros fatores.
            </div>

            <p style="margin: 35px 0 25px; color: var(--text-muted);">Para receber o <strong>relatório detalhado</strong> com todas as verbas e descontos, clique no botão abaixo:</p>

            <a href="<?= $zapUrl ?>" target="_blank" class="whatsapp-cta">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.394 0 12.03c0 2.12.551 4.189 1.597 6.027L0 24l6.135-1.61a11.811 11.811 0 005.908 1.603h.005c6.637 0 12.033-5.395 12.036-12.033a11.976 11.976 0 00-3.532-8.513z"/></svg>
                Receber Relatório no WhatsApp
            </a>

            <div style="margin-top: 30px;">
                <a href="index.php" style="color: var(--text-muted); font-size: 0.9rem;">Fazer outro cálculo</a>
            </div>
        </div>
    </main>
</body>
</html>
