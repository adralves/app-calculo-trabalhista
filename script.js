function formatWhatsapp(value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 2) {
        return digits.length ? `(${digits}` : '';
    }

    if (digits.length <= 7) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function formatCurrency(value, blurry = false) {
    let v = String(value || '').replace(/\D/g, '');
    if (!v) return '';
    
    // Converte para número para formatar separadores de milhar
    let amount = parseInt(v);
    let formatted = amount.toLocaleString('pt-BR');
    
    if (blurry) {
        return 'R$ ' + formatted + ',00';
    }
    return formatted;
}

window.formatWhatsapp = formatWhatsapp;
window.formatCurrency = formatCurrency;

function initCalculatorForm() {
    const form = document.getElementById('calcForm');
    const btnText = document.querySelector('.btn-text');
    const loader = document.querySelector('.loader');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const whatsappInput = document.getElementById('whatsapp');

    if (!form) {
        return;
    }

    function validateWhatsapp() {
        if (!whatsappInput) {
            return true;
        }

        const digits = whatsappInput.value.replace(/\D/g, '');
        const isValid = digits.length === 11;

        whatsappInput.classList.toggle('input-invalid', digits.length > 0 && !isValid);

        if (digits.length === 0) {
            whatsappInput.setCustomValidity('Informe seu WhatsApp com DDD.');
        } else if (!isValid) {
            whatsappInput.setCustomValidity('Digite um WhatsApp completo com DDD.');
        } else {
            whatsappInput.setCustomValidity('');
        }

        return isValid;
    }

    if (whatsappInput && !whatsappInput.dataset.maskReady) {
        whatsappInput.dataset.maskReady = 'true';
        whatsappInput.addEventListener('input', (event) => {
            event.target.value = formatWhatsapp(event.target.value);
            validateWhatsapp();
        });

        whatsappInput.addEventListener('blur', () => {
            validateWhatsapp();
            whatsappInput.reportValidity();
        });
    }

    const salaryInput = document.getElementById('ultimo_salario');
    if (salaryInput && !salaryInput.dataset.maskReady) {
        salaryInput.dataset.maskReady = 'true';
        
        salaryInput.addEventListener('input', (event) => {
            let value = event.target.value.replace(/\D/g, '');
            event.target.value = formatCurrency(value, false);
        });

        salaryInput.addEventListener('blur', (event) => {
            let value = event.target.value.replace(/\D/g, '');
            if (value) {
                event.target.value = formatCurrency(value, true);
            }
        });
        
        salaryInput.addEventListener('focus', (event) => {
            let value = event.target.value.replace(/\D/g, '');
            if (value) {
                event.target.value = value; 
            }
        });
    }

    const nameInput = document.getElementById('nome');
    if (nameInput) {
        nameInput.addEventListener('blur', (event) => {
            event.target.value = event.target.value.toUpperCase();
        });
    }

    if (form.dataset.submitReady === 'true') {
        return;
    }

    form.dataset.submitReady = 'true';

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateWhatsapp() || !form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (btnText) {
            btnText.style.display = 'none';
        }

        if (loader) {
            loader.style.display = 'inline-block';
        }

        if (loadingOverlay) {
            loadingOverlay.classList.remove('hidden');
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Limpa a formatação de moeda antes de enviar
        if (data.ultimo_salario) {
            data.ultimo_salario = data.ultimo_salario.replace(/[R$\s.]/g, '').replace(',', '.');
        }

        try {
            const response = await fetch('api/calcular.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                throw new Error('Erro no calculo. Verifique os dados e tente novamente.');
            }

            const result = await response.json();

            if (result.uuid) {
                const liquido = result.resumo.liquido;
                const nome = data.nome;
                const dbId = result.db_id;

                window.location.href = `resultado.php?id=${result.uuid}&nome=${encodeURIComponent(nome)}&id_calc=${dbId}&valor=${liquido}`;
            } else {
                throw new Error('Erro ao salvar os resultados. Tente novamente.');
            }
        } catch (error) {
            alert(error.message);
        } finally {
            if (btnText) {
                btnText.style.display = 'inline-block';
            }

            if (loader) {
                loader.style.display = 'none';
            }
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCalculatorForm);
} else {
    initCalculatorForm();
}
