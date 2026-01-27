/**
 * SISTEMA ADONIS - PAINEL ADMINISTRATIVO
 * JavaScript centralizado
 * Versão: 1.1
 * Data: 26/01/2026
 */

'use strict';

// ========================================
// CONFIGURAÇÕES GLOBAIS
// ========================================

const ADONIS_ADMIN = {
    apiUrl: '../api/',
    timeout: 30000,
    debug: false
};

// ========================================
// UTILITIES
// ========================================

/**
 * Exibir notificação toast
 * @param {string} message - Mensagem a exibir
 * @param {string} type - Tipo: success, error, warning, info
 * @param {number} duration - Duração em ms (padrão: 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
    // Remover toasts existentes
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    // Criar elemento toast
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${getToastIcon(type)}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(toast);
    
    // Remover após duração
    setTimeout(() => {
        toast.classList.add('toast-fadeout');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Obter ícone do toast por tipo
 */
function getToastIcon(type) {
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    return icons[type] || icons.info;
}

/**
 * Confirmar ação com modal nativo
 * @param {string} message - Mensagem de confirmação
 * @returns {boolean}
 */
function confirmAction(message) {
    return confirm(message);
}

/**
 * Debounce - Atrasar execução de função
 * @param {function} func - Função a executar
 * @param {number} wait - Tempo de espera em ms
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Formatar data para padrão brasileiro
 * @param {string} dateString - Data em formato ISO
 * @returns {string}
 */
function formatDateBR(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ========================================
// AÇÕES DE PEDIDOS
// ========================================

/**
 * Aprovar pedido
 * @param {number} pedidoId - ID do pedido
 */
async function aprovarPedido(pedidoId) {
    if (!confirmAction('Deseja realmente aprovar este pedido?\n\nO cliente será notificado automaticamente.')) {
        return;
    }
    
    try {
        showToast('Processando aprovação...', 'info');
        
        const response = await fetch(`${ADONIS_ADMIN.apiUrl}acoes.php?action=aprovar&id=${pedidoId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pedido_id: pedidoId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('✅ Pedido aprovado com sucesso!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Erro ao aprovar pedido');
        }
        
    } catch (error) {
        console.error('Erro ao aprovar pedido:', error);
        showToast('❌ Erro ao aprovar pedido: ' + error.message, 'error', 5000);
    }
}

/**
 * Reprovar pedido
 * @param {number} pedidoId - ID do pedido
 */
async function reprovarPedido(pedidoId) {
    const motivo = prompt('Por favor, informe o motivo da reprovação:');
    
    if (!motivo || motivo.trim() === '') {
        showToast('Reprovação cancelada. Motivo é obrigatório.', 'warning');
        return;
    }
    
    if (!confirmAction('Confirma a reprovação deste pedido?\n\nO cliente será notificado do motivo.')) {
        return;
    }
    
    try {
        showToast('Processando reprovação...', 'info');
        
        const response = await fetch(`${ADONIS_ADMIN.apiUrl}acoes.php?action=reprovar&id=${pedidoId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pedido_id: pedidoId,
                motivo: motivo.trim()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('✅ Pedido reprovado com sucesso!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.message || 'Erro ao reprovar pedido');
        }
        
    } catch (error) {
        console.error('Erro ao reprovar pedido:', error);
        showToast('❌ Erro ao reprovar pedido: ' + error.message, 'error', 5000);
    }
}

/**
 * Editar pedido
 * @param {number} pedidoId - ID do pedido
 */
function editarPedido(pedidoId) {
    // TODO: Implementar modal de edição
    showToast('🚧 Funcionalidade em desenvolvimento', 'info');
    console.log('Editar pedido:', pedidoId);
}

// ========================================
// FILTROS E BUSCA
// ========================================

/**
 * Filtrar tabela por status
 * @param {string} status - Status para filtrar
 */
function filtrarPorStatus(status) {
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        if (status === 'todos') {
            row.style.display = '';
        } else {
            const badge = row.querySelector('.badge');
            if (badge && badge.textContent.toLowerCase().includes(status.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
    
    showToast(`Filtrado por: ${status}`, 'info', 1500);
}

/**
 * Buscar na tabela
 * @param {string} query - Termo de busca
 */
function buscarNaTabela(query) {
    const rows = document.querySelectorAll('tbody tr');
    const searchTerm = query.toLowerCase().trim();
    
    if (searchTerm === '') {
        rows.forEach(row => row.style.display = '');
        return;
    }
    
    let found = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            found++;
        } else {
            row.style.display = 'none';
        }
    });
    
    if (found === 0) {
        showToast('Nenhum resultado encontrado', 'warning', 2000);
    }
}

// ========================================
// VALIDAÇÕES
// ========================================

/**
 * Validar formulário de login
 * @param {HTMLFormElement} form - Formulário
 * @returns {boolean}
 */
function validarLogin(form) {
    const email = form.querySelector('#email');
    const senha = form.querySelector('#senha');
    
    if (!email.value.trim()) {
        showToast('Por favor, informe o e-mail', 'warning');
        email.focus();
        return false;
    }
    
    if (!validateEmail(email.value)) {
        showToast('E-mail inválido', 'warning');
        email.focus();
        return false;
    }
    
    if (!senha.value) {
        showToast('Por favor, informe a senha', 'warning');
        senha.focus();
        return false;
    }
    
    if (senha.value.length < 6) {
        showToast('Senha deve ter no mínimo 6 caracteres', 'warning');
        senha.focus();
        return false;
    }
    
    return true;
}

/**
 * Validar formato de e-mail
 * @param {string} email
 * @returns {boolean}
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// ========================================
// COPIAR TOKEN
// ========================================

/**
 * Copiar token para área de transferência
 * @param {string} token - Token a copiar
 */
function copiarToken(token) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(token)
            .then(() => {
                showToast('✅ Token copiado!', 'success', 2000);
            })
            .catch(err => {
                console.error('Erro ao copiar:', err);
                fallbackCopyToken(token);
            });
    } else {
        fallbackCopyToken(token);
    }
}

/**
 * Fallback para copiar token (navegadores antigos)
 */
function fallbackCopyToken(token) {
    const textarea = document.createElement('textarea');
    textarea.value = token;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showToast('✅ Token copiado!', 'success', 2000);
    } catch (err) {
        showToast('❌ Erro ao copiar token', 'error');
    }
    
    document.body.removeChild(textarea);
}

// ========================================
// INICIALIZAÇÃO
// ========================================

/**
 * Inicializar funcionalidades ao carregar página
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Log de inicialização
    if (ADONIS_ADMIN.debug) {
        console.log('🚀 Adonis Admin JS inicializado');
    }
    
    // Adicionar evento de busca (se existir input)
    const searchInput = document.querySelector('#searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            buscarNaTabela(e.target.value);
        }, 300));
    }
    
    // Adicionar evento de filtro (se existir select)
    const filterSelect = document.querySelector('#filterStatus');
    if (filterSelect) {
        filterSelect.addEventListener('change', function(e) {
            filtrarPorStatus(e.target.value);
        });
    }
    
    // Validação de formulário de login
    const loginForm = document.querySelector('form[action=""]');
    if (loginForm && document.body.classList.contains('login-body')) {
        loginForm.addEventListener('submit', function(e) {
            if (!validarLogin(this)) {
                e.preventDefault();
            }
        });
    }
    
    // Adicionar botões de ação em detalhes.php
    const btnAprovar = document.querySelector('.btn-success');
    const btnReprovar = document.querySelector('.btn-danger');
    const btnEditar = document.querySelector('.btn-secondary');
    
    // Obter ID do pedido da URL
    const urlParams = new URLSearchParams(window.location.search);
    const pedidoId = urlParams.get('id');
    
    if (btnAprovar && pedidoId) {
        btnAprovar.addEventListener('click', () => aprovarPedido(pedidoId));
    }
    
    if (btnReprovar && pedidoId) {
        btnReprovar.addEventListener('click', () => reprovarPedido(pedidoId));
    }
    
    if (btnEditar && pedidoId) {
        btnEditar.addEventListener('click', () => editarPedido(pedidoId));
    }
    
    // Adicionar funcionalidade de copiar token
    const tokenBox = document.querySelector('.token-box');
    if (tokenBox) {
        tokenBox.style.cursor = 'pointer';
        tokenBox.title = 'Clique para copiar';
        tokenBox.addEventListener('click', function() {
            copiarToken(this.textContent.trim());
        });
    }
    
    // Confirmar logout
    const btnLogout = document.querySelector('.btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', function(e) {
            if (!confirmAction('Deseja realmente sair do painel administrativo?')) {
                e.preventDefault();
            }
        });
    }
    
});

// ========================================
// EXPORTAR FUNÇÕES GLOBAIS
// ========================================

window.ADONIS = {
    aprovarPedido,
    reprovarPedido,
    editarPedido,
    filtrarPorStatus,
    buscarNaTabela,
    copiarToken,
    showToast
};

// ========================================
// ESTILOS PARA TOAST (Injetados dinamicamente)
// ========================================

const toastStyles = document.createElement('style');
toastStyles.textContent = `
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 10000;
    animation: toastSlideIn 0.3s ease;
    max-width: 400px;
}

.toast-success { border-left: 4px solid #4caf50; }
.toast-error { border-left: 4px solid #f44336; }
.toast-warning { border-left: 4px solid #ff9800; }
.toast-info { border-left: 4px solid #2196f3; }

.toast-icon {
    font-size: 20px;
}

.toast-message {
    flex: 1;
    font-size: 14px;
    color: #333;
}

.toast-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast-close:hover {
    color: #333;
}

.toast-fadeout {
    animation: toastSlideOut 0.3s ease;
}

@keyframes toastSlideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes toastSlideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

@media (max-width: 768px) {
    .toast-notification {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
}
`;
document.head.appendChild(toastStyles);