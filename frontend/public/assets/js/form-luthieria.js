/**
 * FORMULÁRIO LUTHIERIA - SISTEMA ADONIS
 * Versão: 5.0
 * Data: 26/01/2026
 */

// ========================================
// CONFIGURAÇÕES GLOBAIS
// ========================================
const API_URL = 'https://adns.luizpimentel.com/adonis-custom/backend/api';

// Cache de elementos DOM
const elementos = {
    form: null,
    servicosContainer: null,
    fotosInput: null,
    fotosPreview: null,
    btnSubmit: null,
    // Campos dinâmicos
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
    enviando: false
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

// ========================================
// INICIALIZAÇÃO
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando formulário v5.0...');
    inicializarElementos();
    carregarServicos();
    configurarEventos();
    configurarCamposDinamicos();
    aplicarMascaras();
});

function inicializarElementos() {
    elementos.form = document.getElementById('formOrcamento');
    elementos.servicosContainer = document.getElementById('servicos-container');
    elementos.fotosInput = document.getElementById('fotos');
    elementos.fotosPreview = document.getElementById('preview');
    elementos.btnSubmit = elementos.form ? elementos.form.querySelector('button[type="submit"]') : null;
    
    // Campos dinâmicos
    elementos.tipo = document.getElementById('tipo');
    elementos.tipoOutro = document.getElementById('tipo_outro');
    elementos.marca = document.getElementById('marca');
    elementos.marcaOutro = document.getElementById('marca_outro');
    elementos.modelo = document.getElementById('modelo');
    elementos.modeloOutro = document.getElementById('modelo_outro');
    elementos.cor = document.getElementById('cor');
    elementos.corOutro = document.getElementById('cor_outro');
    
    // Validação de elementos críticos
    if (!elementos.servicosContainer) {
        console.error('ERRO: Container de serviços não encontrado!');
    }
    if (!elementos.tipo) {
        console.error('ERRO: Campo tipo não encontrado!');
    }
}

// ========================================
// CAMPOS DINÂMICOS (TIPO, MARCA, MODELO, COR)
// ========================================
function configurarCamposDinamicos() {
    console.log('Configurando campos dinâmicos...');
    
    // TIPO - Mostrar campo "Outro" e atualizar modelos
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
            
            // Atualizar modelos
            if (this.value && this.value !== '') {
                atualizarModelos(this.value);
            }
            
            // Controlar visibilidade do campo cor
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
    
    // MARCA - Mostrar campo "Outro"
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
    
    // MODELO - Mostrar campo "Outro"
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
    
    // COR - Mostrar campo "Outra"
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
// CARREGAR SERVIÇOS DO BACKEND
// ========================================
async function carregarServicos() {
    console.log('Carregando serviços...');
    
    try {
        const response = await fetch(`${API_URL}/servicos.php`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
        const resultado = await response.json();
        console.log('Serviços carregados:', resultado);
        
        // Extrai o array de serviços (resultado.data ou resultado direto)
        const servicos = resultado.data || resultado;
        
        if (servicos && servicos.length > 0) {
            renderizarServicos(servicos);
        } else {
            elementos.servicosContainer.innerHTML = '<p style="color: #f44336; padding: 20px; text-align: center;">Nenhum serviço disponível no momento.</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
        elementos.servicosContainer.innerHTML = `
            <div style="color: #f44336; padding: 20px; text-align: center; background: #ffebee; border-radius: 8px; margin: 20px 0;">
                <strong>Erro ao carregar serviços</strong><br>
                <small>Por favor, recarregue a página ou tente novamente mais tarde.</small><br>
                <small style="color: #666; margin-top: 8px; display: block;">Detalhes: ${error.message}</small>
            </div>
        `;
    }
}

function renderizarServicos(servicos) {
    // Agrupar por categoria
    const reparo = servicos.filter(s => s.categoria === 'Reparo');
    const customizacao = servicos.filter(s => s.categoria === 'Customizacao');
    
    let html = '';
    
    // REPARO
    if (reparo.length > 0) {
        html += '<div class="categoria-group" style="margin-bottom: 32px;">';
        html += '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #ff6b35; display: flex; align-items: center; gap: 8px;">';
        html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>';
        html += 'Serviços de Reparo';
        html += '</h3>';
        html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">';
        reparo.forEach(servico => {
            html += criarCheckboxServico(servico);
        });
        html += '</div>';
        html += '</div>';
    }
    
    // CUSTOMIZAÇÃO
    if (customizacao.length > 0) {
        html += '<div class="categoria-group" style="margin-bottom: 32px;">';
        html += '<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #ff6b35; display: flex; align-items: center; gap: 8px;">';
        html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.66 7.93L12 2.27 6.34 7.93c-3.12 3.12-3.12 8.19 0 11.31C7.9 20.8 9.95 21.58 12 21.58c2.05 0 4.1-.78 5.66-2.34 3.12-3.12 3.12-8.19 0-11.31zM12 19.59c-1.6 0-3.11-.62-4.24-1.76C6.62 16.69 6 15.19 6 13.59s.62-3.11 1.76-4.24L12 5.1l4.24 4.24C17.38 10.48 18 12 18 13.59s-.62 3.11-1.76 4.24C15.11 18.97 13.6 19.59 12 19.59z"/></svg>';
        html += 'Customização e Upgrades';
        html += '</h3>';
        html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">';
        customizacao.forEach(servico => {
            html += criarCheckboxServico(servico);
        });
        html += '</div>';
        html += '</div>';
    }
    
    elementos.servicosContainer.innerHTML = html;
    
    // Reconfigurar eventos após renderizar
    configurarCheckboxesServicos();
}

function criarCheckboxServico(servico) {
    return `
        <div class="checkbox-item" style="padding: 12px; background: #f9f9f9; border-radius: 8px; border-left: 3px solid #ddd; transition: all 0.2s ease; height: 100%;">
            <label for="servico_${servico.id}" style="cursor: pointer; display: flex; align-items: start; gap: 8px; height: 100%;">
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
    const checkboxes = document.querySelectorAll('input[name="servicos[]"]');
    checkboxes.forEach(checkbox => {
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
    console.log('Fotos selecionadas:', files.length);
    
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
                        style="position: absolute; top: 4px; right: 4px; background: rgba(244, 67, 54, 0.9); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 16px; line-height: 1;">×</button>
            `;
            elementos.fotosPreview.appendChild(preview);
        };
        reader.readAsDataURL(file);
    });
}

function removerFoto(index) {
    const dt = new DataTransfer();
    const files = Array.from(elementos.fotosInput.files);
    
    files.forEach((file, i) => {
        if (i !== index) dt.items.add(file);
    });
    
    elementos.fotosInput.files = dt.files;
    handleFotosChange({ target: elementos.fotosInput });
}

// ========================================
// MÁSCARAS DE ENTRADA
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
// VALIDAÇÃO E ENVIO DO FORMULÁRIO
// ========================================
async function handleFormSubmit(event) {
    event.preventDefault();
    
    if (estado.enviando) return;
    
    // Validações
    const nome = document.getElementById('cliente_nome').value.trim();
    const telefone = document.getElementById('cliente_telefone').value.trim();
    const email = document.getElementById('cliente_email').value.trim();
    const servicosSelecionados = document.querySelectorAll('input[name="servicos[]"]:checked');
    
    if (nome.length < 3) {
        alert('Por favor, informe seu nome completo.');
        return;
    }
    
    if (telefone.length < 14) {
        alert('Por favor, informe um telefone válido.');
        return;
    }
    
    if (email && !validarEmail(email)) {
        alert('Por favor, informe um e-mail válido.');
        return;
    }
    
    if (servicosSelecionados.length === 0) {
        alert('Por favor, selecione pelo menos um serviço.');
        return;
    }
    
    // Preparar dados
    const formData = new FormData(elementos.form);
    
    // Adicionar serviços selecionados
    const servicosIds = [];
    servicosSelecionados.forEach(checkbox => {
        servicosIds.push(checkbox.value);
    });
    formData.append('servicos', JSON.stringify(servicosIds));
    
    // Enviar
    await enviarFormulario(formData);
}

async function enviarFormulario(formData) {
    estado.enviando = true;
    mostrarLoading(true);
    
    const urlAPI = `${API_URL}/preos.php`;
    console.log('✉️ Enviando formulário para:', urlAPI);
    
    try {
        const response = await fetch(urlAPI, {
            method: 'POST',
            body: formData
        });
        
        console.log('📡 Status da resposta:', response.status);
        
        const textoResposta = await response.text();
        console.log('📄 Resposta bruta do servidor:', textoResposta);
        
        let resultado;
        try {
            resultado = JSON.parse(textoResposta);
        } catch (jsonError) {
            console.error('❌ Erro ao fazer parse do JSON:', jsonError);
            const match = textoResposta.match(/<b>(.*?)<\/b>/);
            const erroExtraido = match ? match[1] : 'Erro no servidor';
            estado.enviando = false;
            mostrarLoading(false);
            alert('Erro no backend: ' + erroExtraido);
            return;
        }
        
        console.log('✅ Resposta JSON:', resultado);
        
        // VERIFICAR SUCESSO
        if (resultado.success === true) {
            console.log('🎉 SUCESSO! Iniciando redirecionamento...');
            
            // CORREÇÃO: Buscar dados dentro de resultado.data
            let token, pedidoId;
            
            // Se os dados estão em resultado.data (nova estrutura)
            if (resultado.data) {
                token = resultado.data.public_token || resultado.data.token;
                pedidoId = resultado.data.id || resultado.data.preos_id;
            } else {
                // Se os dados estão direto em resultado (estrutura antiga)
                token = resultado.token || resultado.public_token;
                pedidoId = resultado.preos_id || resultado.preosid || resultado.id;
            }
            
            const tipoElement = document.getElementById('tipo');
            const tipo = tipoElement ? tipoElement.value : '';
            
            // Validar se token e pedidoId existem
            if (!token || !pedidoId) {
                console.error('❌ Token ou Pedido ID não encontrados na resposta:', resultado);
                console.error('Token extraído:', token);
                console.error('Pedido ID extraído:', pedidoId);
                alert('Erro: Dados incompletos na resposta do servidor');
                estado.enviando = false;
                mostrarLoading(false);
                return;
            }
            
            const urlDestino = `sucesso.html?token=${encodeURIComponent(token)}&pedido=${encodeURIComponent(pedidoId)}&instrumento=${encodeURIComponent(tipo)}`;
            
            console.log('🔗 Token:', token);
            console.log('🔗 Pedido ID:', pedidoId);
            console.log('🔗 Tipo:', tipo);
            console.log('🔗 URL DE DESTINO:', urlDestino);
            console.log('🚀 EXECUTANDO window.location.replace...');
            
            // MÉTODO 1
            window.location.replace(urlDestino);
            
            // MÉTODO 2 (backup)
            setTimeout(function() {
                console.log('⚠️ Tentando backup: window.location.href');
                window.location.href = urlDestino;
            }, 500);
            
            return;
        }
        
        // ERRO
        estado.enviando = false;
        mostrarLoading(false);
        const mensagemErro = resultado.message || resultado.error || 'Erro desconhecido';
        console.error('❌ Erro do servidor:', mensagemErro);
        alert(mensagemErro);
        
    } catch (error) {
        estado.enviando = false;
        mostrarLoading(false);
        console.error('❌ Erro fatal:', error);
        alert('Erro: ' + error.message);
    }
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function mostrarLoading(mostrar) {
    if (elementos.btnSubmit) {
        elementos.btnSubmit.disabled = mostrar;
        elementos.btnSubmit.innerHTML = mostrar 
            ? '<svg class="btn-icon" viewBox="0 0 24 24" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/></svg>Enviando...'
            : '<svg class="btn-icon" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>Enviar Pedido de Orçamento';
    }
}

// LOGS E DEBUG
console.log('✅ Form Luthieria JS v5.0 carregado');
console.log('🔗 API URL:', API_URL);