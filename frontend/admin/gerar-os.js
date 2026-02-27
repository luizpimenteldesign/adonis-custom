const API_URL = 'https://adns.luizpimentel.com/adonis-custom/backend/api';
let preosData = null;
let servicosDetalhes = [];

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const preosId = params.get('id');
    
    if (!preosId) {
        mostrarErro('ID da Pré-OS não informado');
        return;
    }
    
    carregarPreOS(preosId);
    
    document.getElementById('desconto').addEventListener('input', calcularTotais);
    document.getElementById('btn-gerar-os').addEventListener('click', gerarOS);
});

async function carregarPreOS(id) {
    try {
        const response = await fetch(API_URL + '/os.php');
        const resultado = await response.json();
        
        if (!resultado.success) {
            throw new Error(resultado.message);
        }
        
        preosData = resultado.data.find(p => p.id == id);
        
        if (!preosData) {
            throw new Error('Pré-OS não encontrada');
        }
        
        await carregarServicosDetalhes();
        renderizarDados();
        
        document.getElementById('loading').style.display = 'none';
        document.getElementById('conteudo').style.display = 'block';
        
    } catch (error) {
        mostrarErro('Erro ao carregar: ' + error.message);
    }
}

async function carregarServicosDetalhes() {
    try {
        const response = await fetch(API_URL + '/servicos.php');
        const resultado = await response.json();
        
        if (resultado.success) {
            servicosDetalhes = resultado.data;
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
    }
}

function renderizarDados() {
    document.getElementById('preos-id').textContent = preosData.id;
    document.getElementById('cliente-nome').textContent = preosData.cliente_nome;
    document.getElementById('cliente-telefone').textContent = preosData.cliente_telefone;
    document.getElementById('cliente-email').textContent = preosData.cliente_email || '-';
    document.getElementById('preos-data').textContent = formatarData(preosData.criado_em);
    
    document.getElementById('inst-tipo').textContent = preosData.tipo;
    document.getElementById('inst-marca').textContent = preosData.marca;
    document.getElementById('inst-modelo').textContent = preosData.modelo;
    document.getElementById('inst-cor').textContent = preosData.cor;
    
    document.getElementById('observacoes').textContent = preosData.observacoes || 'Sem observações';
    
    renderizarServicos();
    calcularTotais();
}

function renderizarServicos() {
    const servicosIds = JSON.parse(preosData.servicos || '[]');
    const container = document.getElementById('servicos-lista');
    
    if (servicosIds.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum serviço selecionado</p>';
        return;
    }
    
    container.innerHTML = servicosIds.map(id => {
        const servico = servicosDetalhes.find(s => s.id == id);
        if (!servico) return '';
        
        return `
            <div class="servico-item mb-3 p-3 border rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${servico.nome}</strong>
                        <br><small class="text-muted">${servico.descricao || ''}</small>
                    </div>
                    <div class="text-end">
                        <input type="number" 
                               class="form-control form-control-sm text-end" 
                               value="${servico.valor_base}" 
                               min="0" 
                               step="0.01"
                               data-servico-id="${servico.id}"
                               onchange="calcularTotais()"
                               style="width: 120px;">
                        <small class="text-muted">Base: R$ ${parseFloat(servico.valor_base).toFixed(2)}</small>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function calcularTotais() {
    const inputs = document.querySelectorAll('[data-servico-id]');
    let subtotal = 0;
    
    inputs.forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    const desconto = parseFloat(document.getElementById('desconto').value) || 0;
    const valorDesconto = subtotal * (desconto / 100);
    const totalFinal = subtotal - valorDesconto;
    
    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('valor-desconto').textContent = valorDesconto.toFixed(2);
    document.getElementById('total-final').textContent = totalFinal.toFixed(2);
}

async function gerarOS() {
    const btn = document.getElementById('btn-gerar-os');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
    
    try {
        // Coletar valores dos serviços
        const servicosValores = {};
        document.querySelectorAll('[data-servico-id]').forEach(input => {
            servicosValores[input.dataset.servicoId] = parseFloat(input.value);
        });
        
        const dados = {
            pre_os_id: preosData.id,
            desconto: parseFloat(document.getElementById('desconto').value) || 0,
            prazo_estimado: parseInt(document.getElementById('prazo-dias').value) || 7,
            observacoes: document.getElementById('obs-interna').value || '',
            servicos_valores: servicosValores
        };
        
        const response = await fetch(API_URL + '/os.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            alert('OS gerada com sucesso! Número: ' + resultado.data.numero_os);
            window.location.href = 'pre-os-lista.php';
        } else {
            throw new Error(resultado.message);
        }
        
    } catch (error) {
        alert('Erro ao gerar OS: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons">check_circle</span> Gerar OS';
    }
}

function formatarData(data) {
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function mostrarErro(mensagem) {
    document.getElementById('loading').style.display = 'none';
    document.getElementById('erro').style.display = 'block';
    document.getElementById('erro-msg').textContent = mensagem;
}
