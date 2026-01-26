const API_URL = 'https://adns.luizpimentel.com/adonis-custom/backend/api';

document.addEventListener('DOMContentLoaded', carregarPreOS);

async function carregarPreOS() {
    try {
        const response = await fetch(API_URL + '/os.php');
        const resultado = await response.json();

        if (resultado.success) {
            document.getElementById('total-preos').textContent = resultado.total;
            renderizarPreOS(resultado.data);
            document.getElementById('loading').style.display = 'none';
            document.getElementById('lista-preos').style.display = 'flex';
        } else {
            mostrarErro(resultado.message);
        }
    } catch (error) {
        mostrarErro('Erro ao carregar Pré-OS: ' + error.message);
    }
}

function renderizarPreOS(preos) {
    const container = document.getElementById('lista-preos');
    
    if (preos.length === 0) {
        container.innerHTML = '<div class="col-12"><p class="text-center text-muted">Nenhuma Pré-OS pendente</p></div>';
        return;
    }

    container.innerHTML = preos.map(p => `
        <div class="col-md-6 col-lg-4">
            <div class="card card-preos h-100" onclick="abrirPreOS(${p.id})">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <strong>Pré-OS #${p.id}</strong>
                    <span class="badge bg-light text-dark">
                        <span class="material-icons" style="font-size: 14px;">schedule</span>
                        ${formatarData(p.criado_em)}
                    </span>
                </div>
                <div class="card-body">
                    <h6 class="card-title">
                        <span class="material-icons" style="font-size: 18px;">person</span>
                        ${p.cliente_nome}
                    </h6>
                    <p class="mb-1">
                        <span class="material-icons" style="font-size: 16px;">phone</span>
                        <small>${p.cliente_telefone}</small>
                    </p>
                    <hr>
                    <p class="mb-1">
                        <span class="material-icons" style="font-size: 18px;">music_note</span>
                        <strong>${p.tipo}</strong> ${p.marca} ${p.modelo}
                    </p>
                    <p class="mb-0">
                        <span class="material-icons" style="font-size: 16px;">palette</span>
                        <small>${p.cor}</small>
                    </p>
                </div>
                <div class="card-footer">
                    <button class="btn btn-sm btn-success w-100">
                        <span class="material-icons" style="font-size: 16px;">check_circle</span>
                        Converter em OS
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function abrirPreOS(id) {
    window.location.href = 'gerar-os.php?id=' + id;
}

function formatarData(data) {
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function mostrarErro(mensagem) {
    document.getElementById('loading').style.display = 'none';
    document.getElementById('erro').style.display = 'block';
    document.getElementById('erro-msg').textContent = mensagem;
}
