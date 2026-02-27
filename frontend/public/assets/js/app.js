let servicosSelecionados = [];

document.addEventListener('DOMContentLoaded', async function() {
    await carregarServicos();

    document.getElementById('formPreOS').addEventListener('submit', async function(e) {
        e.preventDefault();
        await enviarPreOS();
    });
});

async function carregarServicos() {
    try {
        const response = await api.listarServicos();
        const container = document.getElementById('servicos-lista');

        if (response.success && response.data.length > 0) {
            let html = '';
            response.data.forEach(function(servico) {
                html += '<div class="form-check mb-2">';
                html += '<input class="form-check-input" type="checkbox" value="' + servico.id + '" id="servico' + servico.id + '">';
                html += '<label class="form-check-label" for="servico' + servico.id + '">';
                html += '<strong>' + servico.nome + '</strong> - ' + (servico.descricao || '');
                html += '</label>';
                html += '</div>';
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-muted">Nenhum serviço disponível.</p>';
        }
    } catch (error) {
        showAlert('Erro ao carregar serviços: ' + error.message, 'danger');
    }
}

async function enviarPreOS() {
    const nome = document.getElementById('nome').value;
    const telefone = document.getElementById('telefone').value;
    const email = document.getElementById('email').value;
    const observacoes = document.getElementById('observacoes').value;

    const checkboxes = document.querySelectorAll('#servicos-lista input[type="checkbox"]:checked');
    servicosSelecionados = [];
    checkboxes.forEach(function(cb) {
        servicosSelecionados.push(parseInt(cb.value));
    });

    if (servicosSelecionados.length === 0) {
        showAlert('Selecione pelo menos um serviço', 'warning');
        return;
    }

    const btnEnviar = document.getElementById('btnEnviar');
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

    try {
        const response = await api.criarPreOS({
            nome: nome,
            telefone: telefone,
            email: email,
            observacoes: observacoes,
            servicos: servicosSelecionados
        });

        if (response.success) {
            document.getElementById('numeroPreOS').textContent = response.data.numero_pre_os;
            document.getElementById('valorEstimado').textContent = response.data.valor_estimado.toFixed(2);

            const modal = new bootstrap.Modal(document.getElementById('modalSucesso'));
            modal.show();

            document.getElementById('formPreOS').reset();
        }
    } catch (error) {
        showAlert('Erro ao enviar: ' + error.message, 'danger');
    } finally {
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = '<i class="bi bi-send"></i> Enviar Pedido';
    }
}

function showAlert(message, type) {
    const container = document.getElementById('alert-container');
    container.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>';
}
