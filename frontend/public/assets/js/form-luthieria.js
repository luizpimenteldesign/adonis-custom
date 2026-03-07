/**
 * FORMULÁRIO LUTHIERIA - SISTEMA ADONIS
 * Versão: 6.0 - PWA + Mobile-First + Accordion/Tabs
 * Data: 07/03/2026
 */

// ========================================
// CONFIGURAÇÕES GLOBAIS
// ========================================
const API_URL = '/backend/api';

// Cache de elementos DOM
const elementos = {
    form: null,
    servicosContainer: null,
    fotosInput: null,
    fotosPreview: null,
    btnSubmit: null,
    tipo: null,
    tipoOutro: null,
    marca: null,
    marcaOutro: null,
    modelo: null,
    modeloOutro: null,
    cor: null,
    corOutro: null
};

// Estado da aplicação
const estado = {
    servicosSelecionados: [],
    fotosSelecionadas: [],
    enviando: false,
    categoriaAtiva: null,
    servicosPorCategoria: {},
    isMobile: window.innerWidth < 768
};

// Modelos por tipo de instrumento
const modelosPorTipo = {
    'Guitarra': ['Stratocaster', 'Telecaster', 'Les Paul', 'SG', 'Flying V', 'Explorer', 'Superstrat', 'Hollow Body', 'Semi-Hollow', 'Outro'],
    'Baixo': ['Precision Bass', 'Jazz Bass', 'Music Man', 'Thunderbird', 'Warwick', 'Rickenbacker', 'Hollow Body', 'Outro'],
    'Violao': ['Folk', 'Clássico', 'Jumbo', 'Dreadnought', '12 Cordas', 'Outro'],
    'Viola Caipira': ['10 Cordas', '12 Cordas', 'Outro'],
    'Cavaquinho': ['Tradicional', 'Elétrico', 'Outro'],
    'Ukulele': ['Soprano', 'Concert', 'Tenor', 'Barítono', 'Outro'],
    'Bandolim': ['Tradicional', 'Elétrico', 'Outro'],
    'Amplificador': ['Valvulado', 'Transistor', 'Híbrido', 'Modelagem Digital', 'Outro'],
    'Pedal/Pedalboard': ['Overdrive', 'Distortion', 'Delay', 'Reverb', 'Chorus', 'Flanger', 'Wah', 'Compressor', 'Pedalboard Completa', 'Outro'],
    'Outro': ['Outro']
};

// Configuração de categorias
const configCategorias = {
    'Reparo': {
        titulo: 'Reparo',
        icone: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>'
    },
    'Customizacao': {
        titulo: 'Customização',
        icone: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.66 7.93L12 2.27 6.34 7.93c-3.12 3.12-3.12 8.19 0 11.31C7.9 20.8 9.95 21.58 12 21.58c2.05 0 4.1-.78 5.66-2.34 3.12-3.12 3.12-8.19 0-11.31zM12 19.59c-1.6 0-3.11-.62-4.24-1.76C6.62 16.69 6 15.19 6 13.59s.62-3.11 1.76-4.24L12 5.1l4.24 4.24C17.38 10.48 18 12 18 13.59s-.62 3.11-1.76 4.24C15.11 18.97 13.6 19.59 12 19.59z"/></svg>'
    },
    'Customização': {
        titulo: 'Customização',
        icone: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.66 7.93L12 2.27 6.34 7.93c-3.12 3.12-3.12 8.19 0 11.31C7.9 20.8 9.95 21.58 12 21.58c2.05 0 4.1-.78 5.66-2.34 3.12-3.12 3.12-8.19 0-11.31zM12 19.59c-1.6 0-3.11-.62-4.24-1.76C6.62 16.69 6 15.19 6 13.59s.62-3.11 1.76-4.24L12 5.1l4.24 4.24C17.38 10.48 18 12 18 13.59s-.62 3.11-1.76 4.24C15.11 18.97 13.6 19.59 12 19.59z"/></svg>'
    },
    'Regulagem': {
        titulo: 'Regulagem',
        icone: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"/></svg>'
    },
    'Construcao': {
        titulo: 'Construção',
        icone: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.61 16.01L13 6.4V3L9.37 6.63l2.11 2.11-2.19 2.19-2.83-2.83-5.65 5.66 1.41 1.41 4.24-4.24 2.83 2.83 5.66-5.66-1.01-1.01 8.2 8.2c.39.39 1.02.39 1.41 0l.06-.06c.39-.39.39-1.02 0-1.42zm-10.55.96L4 9v3l8.06 8.07c.39.39 1.02.39 1.41 0l5.32-5.32-1.41-1.41-3.32 3.63z"/></svg>'
    },
    'Construção': {
        titulo: 'Construção',
        icone: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.61 16.01L13 6.4V3L9.37 6.63l2.11 2.11-2.19 2.19-2.83-2.83-5.65 5.66 1.41 1.41 4.24-4.24 2.83 2.83 5.66-5.66-1.01-1.01 8.2 8.2c.39.39 1.02.39 1.41 0l.06-.06c.39-.39.39-1.02 0-1.42zm-10.55.96L4 9v3l8.06 8.07c.39.39 1.02.39 1.41 0l5.32-5.32-1.41-1.41-3.32 3.63z"/></svg>'
    }
};

// ========================================
// INICIALIZAÇÃO
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando formulário v6.0 PWA...');
    inicializarElementos();
    carregarServicos();
    configurarEventos();
    configurarCamposDinamicos();
    aplicarMascaras();
    detectarResize();
});

function inicializarElementos() {
    elementos.form = document.getElementById('formOrcamento');
    elementos.servicosContainer = document.getElementById('servicos-container');
    elementos.fotosInput = document.getElementById('fotos');
    elementos.fotosPreview = document.getElementById('preview');
    elementos.btnSubmit = elementos.form ? elementos.form.querySelector('button[type="submit"]') : null;
    
    elementos.tipo = document.getElementById('tipo');
    elementos.tipoOutro = document.getElementById('tipo_outro');
    elementos.marca = document.getElementById('marca');
    elementos.marcaOutro = document.getElementById('marca_outro');
    elementos.modelo = document.getElementById('modelo');
    elementos.modeloOutro = document.getElementById('modelo_outro');
    elementos.cor = document.getElementById('cor');
    elementos.corOutro = document.getElementById('cor_outro');
    
    if (!elementos.servicosContainer) {
        console.error('ERRO: Container de serviços não encontrado!');
    }
    if (!elementos.tipo) {
        console.error('ERRO: Campo tipo não encontrado!');
    }
}

function detectarResize() {
    window.addEventListener('resize', function() {
        const novoIsMobile = window.innerWidth < 768;
        if (novoIsMobile !== estado.isMobile) {
            estado.isMobile = novoIsMobile;
            if (Object.keys(estado.servicosPorCategoria).length > 0) {
                renderizarServicosResponsivo();
            }
        }
    });
}

// ========================================
// CAMPOS DINÂMICOS
// ========================================
function configurarCamposDinamicos() {
    if (elementos.tipo) {
        elementos.tipo.addEventListener('change', function() {
            const campoTipoOutro = document.getElementById('campo_tipo_outro');
            
            if (this.value === 'Outro') {
                campoTipoOutro.classList.remove('hidden');
                elementos.tipoOutro.required = true;
            } else {
                campoTipoOutro.classList.add('hidden');
                elementos.tipoOutro.required = false;
                elementos.tipoOutro.value = '';
            }
            
            if (this.value && this.value !== '') {
                atualizarModelos(this.value);
            }
            
            const tiposSemCor = ['Amplificador', 'Pedal/Pedalboard'];
            const campoCor = document.getElementById('campo_cor');
            const corRequired = campoCor ? campoCor.querySelector('.cor-required') : null;
            
            if (tiposSemCor.includes(this.value)) {
                if (campoCor) campoCor.classList.add('hidden');
                if (elementos.cor) elementos.cor.required = false;
                if (corRequired) corRequired.style.display = 'none';
            } else {
                if (campoCor) campoCor.classList.remove('hidden');
                if (elementos.cor) elementos.cor.required = true;
                if (corRequired) corRequired.style.display = 'inline';
            }
        });
    }
    
    if (elementos.marca) {
        elementos.marca.addEventListener('change', function() {
            const campoMarcaOutro = document.getElementById('campo_marca_outro');
            
            if (this.value === 'Outro') {
                campoMarcaOutro.classList.remove('hidden');
                elementos.marcaOutro.required = true;
            } else {
                campoMarcaOutro.classList.add('hidden');
                elementos.marcaOutro.required = false;
                elementos.marcaOutro.value = '';
            }
        });
    }
    
    if (elementos.modelo) {
        elementos.modelo.addEventListener('change', function() {
            const campoModeloOutro = document.getElementById('campo_modelo_outro');
            
            if (this.value === 'Outro') {
                campoModeloOutro.classList.remove('hidden');
                elementos.modeloOutro.required = true;
            } else {
                campoModeloOutro.classList.add('hidden');
                elementos.modeloOutro.required = false;
                elementos.modeloOutro.value = '';
            }
        });
    }
    
    if (elementos.cor) {
        elementos.cor.addEventListener('change', function() {
            const campoCorOutro = document.getElementById('campo_cor_outro');
            
            if (this.value === 'Outra') {
                campoCorOutro.classList.remove('hidden');
                elementos.corOutro.required = true;
            } else {
                campoCorOutro.classList.add('hidden');
                elementos.corOutro.required = false;
                elementos.corOutro.value = '';
            }
        });
    }
}

function atualizarModelos(tipo) {
    const modelos = modelosPorTipo[tipo];
    
    if (modelos && modelos.length > 0) {
        elementos.modelo.disabled = false;
        elementos.modelo.innerHTML = '<option value="">Selecione...</option>';
        
        modelos.forEach(function(modelo) {
            const option = document.createElement('option');
            option.value = modelo;
            option.textContent = modelo;
            elementos.modelo.appendChild(option);
        });
    } else {
        elementos.modelo.disabled = true;
        elementos.modelo.innerHTML = '<option value="">Nenhum modelo disponível</option>';
    }
}

// ========================================
// CARREGAR SERVIÇOS
// ========================================
async function carregarServicos() {
    try {
        const response = await fetch(`${API_URL}/servicos.php`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const resultado = await response.json();
        const servicos = resultado.data || resultado;
        
        if (servicos && servicos.length > 0) {
            // Agrupar por categoria
            const categorias = {};
            servicos.forEach(servico => {
                const cat = servico.categoria || 'Outros';
                if (!categorias[cat]) categorias[cat] = [];
                categorias[cat].push(servico);
            });
            
            estado.servicosPorCategoria = categorias;
            renderizarServicosResponsivo();
        } else {
            elementos.servicosContainer.innerHTML = '<p style="color: #f44336; padding: 20px; text-align: center;">Nenhum serviço disponível.</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
        elementos.servicosContainer.innerHTML = `
            <div style="color: #f44336; padding: 20px; text-align: center; background: #ffebee; border-radius: 8px;">
                <strong>Erro ao carregar serviços</strong><br>
                <small>${error.message}</small>
            </div>
        `;
    }
}

function renderizarServicosResponsivo() {
    if (estado.isMobile) {
        renderizarAccordion();
    } else {
        renderizarTabs();
    }
}

// ========================================
// ACCORDION (MOBILE)
// ========================================
function renderizarAccordion() {
    const categorias = estado.servicosPorCategoria;
    const ordemCategorias = ['Reparo', 'Customizacao', 'Customização', 'Regulagem', 'Construcao', 'Construção'];
    const categoriasDisponiveis = ordemCategorias.filter(cat => categorias[cat] && categorias[cat].length > 0);
    
    if (categoriasDisponiveis.length === 0) {
        elementos.servicosContainer.innerHTML = '<p style="color: #999; text-align: center;">Nenhuma categoria disponível.</p>';
        return;
    }
    
    let html = `
        <style>
            .accordion-item {
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 12px;
                overflow: hidden;
                background: white;
            }
            .accordion-header {
                padding: 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: white;
                transition: background 0.2s;
                user-select: none;
            }
            .accordion-header:active {
                background: #f5f5f5;
            }
            .accordion-title {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 15px;
                font-weight: 500;
                color: #333;
            }
            .accordion-count {
                font-size: 13px;
                color: #999;
                font-weight: 400;
            }
            .accordion-icon {
                transition: transform 0.3s ease;
                color: #999;
            }
            .accordion-icon.open {
                transform: rotate(180deg);
            }
            .accordion-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }
            .accordion-content.open {
                max-height: 2000px;
            }
            .accordion-body {
                padding: 0 16px 16px 16px;
                display: grid;
                gap: 10px;
            }
        </style>
    `;
    
    categoriasDisponiveis.forEach((catKey, index) => {
        const config = configCategorias[catKey] || { titulo: catKey, icone: '' };
        const count = categorias[catKey].length;
        const isFirst = index === 0;
        
        html += `
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        ${config.icone}
                        <span>${config.titulo} <span class="accordion-count">(${count})</span></span>
                    </div>
                    <svg class="accordion-icon ${isFirst ? 'open' : ''}" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 10l5 5 5-5z"/>
                    </svg>
                </div>
                <div class="accordion-content ${isFirst ? 'open' : ''}">
                    <div class="accordion-body">
        `;
        
        categorias[catKey].forEach(servico => {
            html += criarCheckboxServico(servico);
        });
        
        html += `
                    </div>
                </div>
            </div>
        `;
    });
    
    elementos.servicosContainer.innerHTML = html;
    configurarCheckboxesServicos();
}

window.toggleAccordion = function(header) {
    const icon = header.querySelector('.accordion-icon');
    const content = header.nextElementSibling;
    
    icon.classList.toggle('open');
    content.classList.toggle('open');
};

// ========================================
// TABS (DESKTOP)
// ========================================
function renderizarTabs() {
    const categorias = estado.servicosPorCategoria;
    const ordemCategorias = ['Reparo', 'Customizacao', 'Customização', 'Regulagem', 'Construcao', 'Construção'];
    const categoriasDisponiveis = ordemCategorias.filter(cat => categorias[cat] && categorias[cat].length > 0);
    
    if (categoriasDisponiveis.length === 0) return;
    
    estado.categoriaAtiva = categoriasDisponiveis[0];
    
    let html = `
        <style>
            .tabs-container { margin-bottom: 24px; }
            .tabs-nav {
                display: flex;
                gap: 8px;
                border-bottom: 2px solid #e0e0e0;
                margin-bottom: 20px;
            }
            .tab-button {
                flex: 1;
                padding: 12px 16px;
                background: transparent;
                border: none;
                border-bottom: 3px solid transparent;
                color: #666;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }
            .tab-button:hover {
                color: #ff6b35;
                background: #fff3e0;
            }
            .tab-button.active {
                color: #ff6b35;
                border-bottom-color: #ff6b35;
                background: #fff3e0;
            }
            .tab-content { display: none; animation: fadeIn 0.3s; }
            .tab-content.active { display: block; }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .servicos-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 12px;
            }
        </style>
        <div class="tabs-container">
            <div class="tabs-nav">
    `;
    
    categoriasDisponiveis.forEach(catKey => {
        const config = configCategorias[catKey] || { titulo: catKey, icone: '' };
        const isActive = catKey === estado.categoriaAtiva;
        const count = categorias[catKey].length;
        
        html += `
            <button type="button" class="tab-button ${isActive ? 'active' : ''}" data-categoria="${catKey}" onclick="mudarCategoria('${catKey}')">
                ${config.icone}
                <span>${config.titulo} <small style="opacity: 0.7;">(${count})</small></span>
            </button>
        `;
    });
    
    html += `</div>`;
    
    categoriasDisponiveis.forEach(catKey => {
        const isActive = catKey === estado.categoriaAtiva;
        html += `<div class="tab-content ${isActive ? 'active' : ''}" data-categoria="${catKey}"><div class="servicos-grid">`;
        categorias[catKey].forEach(servico => html += criarCheckboxServico(servico));
        html += `</div></div>`;
    });
    
    html += `</div>`;
    elementos.servicosContainer.innerHTML = html;
    configurarCheckboxesServicos();
}

window.mudarCategoria = function(categoria) {
    estado.categoriaAtiva = categoria;
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.categoria === categoria);
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.dataset.categoria === categoria);
    });
};

// ========================================
// CHECKBOX SERVIÇO
// ========================================
function criarCheckboxServico(servico) {
    return `
        <div class="checkbox-item" style="padding: 12px; background: #f9f9f9; border-radius: 8px; border-left: 3px solid #ddd; transition: all 0.2s;">
            <label for="servico_${servico.id}" style="cursor: pointer; display: flex; align-items: start; gap: 8px;">
                <input type="checkbox" name="servicos[]" value="${servico.id}" id="servico_${servico.id}" 
                       data-nome="${servico.nome}" data-valor="${servico.valor_base}" data-prazo="${servico.prazo_base}"
                       style="margin-top: 2px; width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;">
                <span style="flex: 1;">
                    <strong style="color: #333; font-size: 13px; display: block; margin-bottom: 4px;">${servico.nome}</strong>
                    ${servico.descricao ? `<small style="color: #666; font-size: 11px; line-height: 1.3; display: block;">${servico.descricao}</small>` : ''}
                </span>
            </label>
        </div>
    `;
}

function configurarCheckboxesServicos() {
    document.querySelectorAll('input[name="servicos[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const item = checkbox.closest('.checkbox-item');
            if (checkbox.checked) {
                item.style.background = '#fff3e0';
                item.style.borderLeftColor = '#ff6b35';
                item.style.borderLeftWidth = '4px';
            } else {
                item.style.background = '#f9f9f9';
                item.style.borderLeftColor = '#ddd';
                item.style.borderLeftWidth = '3px';
            }
        });
    });
}

// ========================================
// UPLOAD DE FOTOS
// ========================================
function configurarEventos() {
    if (elementos.fotosInput) {
        elementos.fotosInput.addEventListener('change', handleFotosChange);
    }
    if (elementos.form) {
        elementos.form.addEventListener('submit', handleFormSubmit);
    }
}

function handleFotosChange(event) {
    const files = Array.from(event.target.files);
    estado.fotosSelecionadas = files;
    
    if (elementos.fotosPreview) {
        renderizarPreviewFotos(files);
    }
}

function renderizarPreviewFotos(files) {
    elementos.fotosPreview.innerHTML = '';
    if (files.length === 0) return;
    
    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('div');
            preview.style.cssText = 'position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden; border: 2px solid #ddd;';
            preview.innerHTML = `
                <img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover;">
                <button type="button" onclick="removerFoto(${index})" 
                        style="position: absolute; top: 4px; right: 4px; background: rgba(244, 67, 54, 0.9); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 16px;">×</button>
            `;
            elementos.fotosPreview.appendChild(preview);
        };
        reader.readAsDataURL(file);
    });
}

window.removerFoto = function(index) {
    const dt = new DataTransfer();
    const files = Array.from(elementos.fotosInput.files);
    files.forEach((file, i) => {
        if (i !== index) dt.items.add(file);
    });
    elementos.fotosInput.files = dt.files;
    handleFotosChange({ target: elementos.fotosInput });
};

// ========================================
// MÁSCARAS
// ========================================
function aplicarMascaras() {
    const telefoneInput = document.getElementById('cliente_telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4,5})(\d{4})/, '$1-$2');
            }
            e.target.value = value;
        });
    }
}

// ========================================
// ENVIO DO FORMULÁRIO
// ========================================
async function handleFormSubmit(event) {
    event.preventDefault();
    if (estado.enviando) return;
    
    const nome = document.getElementById('cliente_nome').value.trim();
    const telefone = document.getElementById('cliente_telefone').value.trim();
    const email = document.getElementById('cliente_email').value.trim();
    const servicosSelecionados = document.querySelectorAll('input[name="servicos[]"]:checked');
    
    if (nome.length < 3) { alert('Nome completo é obrigatório.'); return; }
    if (telefone.length < 14) { alert('Telefone válido é obrigatório.'); return; }
    if (email && !validarEmail(email)) { alert('E-mail inválido.'); return; }
    if (servicosSelecionados.length === 0) { alert('Selecione pelo menos um serviço.'); return; }
    
    const formData = new FormData(elementos.form);
    const servicosIds = [];
    servicosSelecionados.forEach(checkbox => servicosIds.push(checkbox.value));
    formData.append('servicos', JSON.stringify(servicosIds));
    
    await enviarFormulario(formData);
}

async function enviarFormulario(formData) {
    estado.enviando = true;
    mostrarLoading(true);
    
    try {
        const response = await fetch(`${API_URL}/preos.php`, { method: 'POST', body: formData });
        const textoResposta = await response.text();
        
        let resultado;
        try {
            resultado = JSON.parse(textoResposta);
        } catch (jsonError) {
            const match = textoResposta.match(/<b>(.*?)<\/b>/);
            throw new Error(match ? match[1] : 'Erro no servidor');
        }
        
        if (resultado.success === true) {
            let token, pedidoId;
            if (resultado.data) {
                token = resultado.data.public_token || resultado.data.token;
                pedidoId = resultado.data.id || resultado.data.preos_id;
            } else {
                token = resultado.token || resultado.public_token;
                pedidoId = resultado.preos_id || resultado.preosid || resultado.id;
            }
            
            const tipo = elementos.tipo ? elementos.tipo.value : '';
            
            if (!token || !pedidoId) {
                throw new Error('Dados incompletos na resposta do servidor');
            }
            
            const urlDestino = `sucesso.html?token=${encodeURIComponent(token)}&pedido=${encodeURIComponent(pedidoId)}&instrumento=${encodeURIComponent(tipo)}`;
            window.location.replace(urlDestino);
            setTimeout(() => window.location.href = urlDestino, 500);
            return;
        }
        
        throw new Error(resultado.message || resultado.error || 'Erro desconhecido');
        
    } catch (error) {
        estado.enviando = false;
        mostrarLoading(false);
        alert('Erro: ' + error.message);
    }
}

function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function mostrarLoading(mostrar) {
    if (elementos.btnSubmit) {
        elementos.btnSubmit.disabled = mostrar;
        elementos.btnSubmit.innerHTML = mostrar 
            ? '<svg class="btn-icon" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/></svg>Enviando...'
            : '<svg class="btn-icon" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Enviar Pedido de Orçamento';
    }
}

console.log('✅ Form Luthieria JS v6.0 PWA carregado');
console.log('📱 Mobile:', estado.isMobile);