<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora Trabalhista | Rescisão Fácil</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="main-header">
        <div class="header-container">
            <a href="https://amaralecastroadvocacia.com/" class="logo">
                <img src="assets/img/logo-principal.png" alt="Logo Principal">
            </a>
            <nav class="main-nav">
                <a href="https://amaralecastroadvocacia.com/">Home</a>
                <a href="#" class="active gold-text gold-border">Calculadora</a>
                <a href="https://api.whatsapp.com/send?phone=5585991562067&text=Ol%C3%A1,%20vim%20pelo%20site,%20gostaria%20de%20falar%20com%20um%20advogado."
                    class="btn-whatsapp">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .018 5.394 0 12.03c0 2.12.551 4.189 1.597 6.027L0 24l6.135-1.61a11.811 11.811 0 005.908 1.603h.005c6.637 0 12.033-5.395 12.036-12.033a11.976 11.976 0 00-3.532-8.513z" />
                    </svg>
                    Fale Conosco
                </a>
            </nav>
        </div>
        <div class="hero-section">
            <h1 class="hero-title gold-text">Calculadora de Rescisão</h1>
            <p class="hero-subtitle">Calcule de forma simples e rápida os direitos trabalhistas.</p>
        </div>
    </header>

    <main class="main-content">
        <section class="calculator-container card">
            <form id="calcForm" class="calc-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="Digite seu nome..." required>
                    </div>

                    <div class="form-group">
                        <label for="whatsapp">WhatsApp (com DDD)</label>
                        <input type="tel" id="whatsapp" name="whatsapp" placeholder="Digite seu numero..."
                            maxlength="15" inputmode="numeric" pattern="^\(\d{2}\)\s\d{5}-\d{4}$"
                            oninput="this.value = window.formatWhatsapp ? window.formatWhatsapp(this.value) : this.value"
                            required>
                    </div>

                    <div class="form-group full-width">
                        <label for="motivo">Motivo da Rescisão</label>
                        <select id="motivo" name="motivo" required>
                            <option value="">Selecione o motivo...</option>
                            <option value="dispensa_sem_justa_causa">Dispensa sem Justa Causa</option>
                            <option value="pedido_demissao">Pedido de Demissao</option>
                            <option value="dispensa_com_justa_causa">Dispensa com Justa Causa</option>
                            <option value="termino_contrato">Termino de Contrato</option>
                            <option value="acordo">Rescisão por Acordo (Reforma Trabalhista)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="data_admissao">Data de contratacão</label>
                        <input type="date" id="data_admissao" name="data_admissao" required>
                    </div>

                    <div class="form-group">
                        <label for="data_demissao">Data da demissão</label>
                        <input type="date" id="data_demissao" name="data_demissao" required>
                    </div>

                    <div class="form-group">
                        <label for="ultimo_salario">Qual o seu salario? (R$)</label>
                        <input type="tel" id="ultimo_salario" name="ultimo_salario"
                            placeholder="R$ 0,00" required>
                    </div>

                    <div class="form-group">
                        <label for="aviso_previo">Tipo de Aviso Previo</label>
                        <select id="aviso_previo" name="aviso_previo" required>
                            <option value="indenizado">Indenizado pelo Empregador</option>
                            <option value="trabalhado">Trabalhado</option>
                            <option value="nao_cumprido">Nao Cumprido (Desconto)</option>
                            <option value="nenhum">Nenhum / Nao Se Aplica</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="ferias_vencidas">Ferias Vencidas (anos s/ tirar)</label>
                        <input type="number" id="ferias_vencidas" name="ferias_vencidas" min="0" max="10"
                            placeholder="Ex: 0, 1, 2" value="0" required>
                        <small>Deixe 0 se nao houver.</small>
                    </div>

                    <div class="form-group">
                        <label for="dependentes">Num. de Dependentes</label>
                        <input type="number" id="dependentes" name="dependentes" min="0" max="20"
                            placeholder="Para IRRF" value="0" required>
                    </div>
                </div>

                <div class="form-note" style="margin-top: 0.75rem; text-align: center;">
                    <p style="font-size: 0.78rem; color: #94a3b8; line-height: 1.4;">Seus dados estao seguros e não
                        serão compartilhados.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnCalcular">
                        <span class="btn-text">Calcular meus direitos</span>
                        <span class="loader" style="display:none;"></span>
                    </button>
                </div>
            </form>

            <div id="loadingOverlay" class="hidden">
                <div class="loader-container">
                    <div class="loader-large"></div>
                    <p>Processando seu calculo...</p>
                </div>
            </div>
        </section>
    </main>
    <script src="script.js"></script>
</body>

</html>