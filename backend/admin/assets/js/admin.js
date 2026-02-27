/**
 * SISTEMA ADONIS - PAINEL ADMINISTRATIVO
 * JavaScript centralizado
 * Versão: 2.0
 * Data: 27/02/2026
 */

'use strict';

// ========================================
// TOAST
// ========================================

function showToast(message, type = 'info', duration = 3000) {
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('toast-fadeout');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ========================================
// ATUALIZAR STATUS (usado em detalhes.php)
// ========================================

const statusLabels = {
    'Pre-OS':               '🗒️ Pré-OS',
    'Em analise':           '🔍 Em Análise',
    'Orcada':               '💰 Orçada',
    'Aguardando aprovacao': '⏳ Aguardando Aprovação',
    'Aprovada':             '✅ Aprovada',
    'Reprovada':            '❌ Reprovada',
    'Cancelada':            '🚫 Cancelada',
};

const statusClasses = {
    'Pre-OS':               'badge-new',
    'Em analise':           'badge-info',
    'Orcada':               'badge-warning',
    'Aguardando aprovacao': 'badge-warning',
    'Aprovada':             'badge-success',
    'Reprovada':            'badge-danger',
    'Cancelada':            'badge-dark',
};

function atualizarStatus(novoStatus) {
    const urlParams = new URLSearchParams(window.location.search);
    const pedidoId  = parseInt(urlParams.get('id'));

    if (!pedidoId) return;
    if (!confirm('Alterar status para "' + statusLabels[novoStatus] + '"?')) return;

    fetch('atualizar_status.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: pedidoId, status: novoStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            const badge = document.getElementById('status-badge');
            if (badge) {
                badge.innerHTML = '<span class="badge ' + statusClasses[novoStatus] + '">' + statusLabels[novoStatus] + '</span>';
            }
            const atualizado = document.getElementById('atualizado-em');
            if (atualizado) atualizado.textContent = data.atualizado_em;
            showToast('✅ Status atualizado com sucesso!', 'success');
        } else {
            showToast('❌ Erro: ' + (data.erro || 'Falha desconhecida'), 'error', 5000);
        }
    })
    .catch(() => showToast('❌ Erro de conexão', 'error'));
}

// ========================================
// COPIAR TOKEN
// ========================================

function copiarToken(token) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(token)
            .then(() => showToast('✅ Token copiado!', 'success', 2000))
            .catch(() => fallbackCopiarToken(token));
    } else {
        fallbackCopiarToken(token);
    }
}

function fallbackCopiarToken(token) {
    const ta = document.createElement('textarea');
    ta.value = token;
    ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showToast('✅ Token copiado!', 'success', 2000);
    } catch(e) {
        showToast('❌ Erro ao copiar token', 'error');
    }
    document.body.removeChild(ta);
}

// ========================================
// VALIDAÇÕES
// ========================================

function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validarLogin(form) {
    const email = form.querySelector('#email');
    const senha = form.querySelector('#senha');
    if (!email.value.trim())          { showToast('Informe o e-mail', 'warning'); email.focus(); return false; }
    if (!validateEmail(email.value))  { showToast('E-mail inválido', 'warning'); email.focus(); return false; }
    if (!senha.value)                 { showToast('Informe a senha', 'warning'); senha.focus(); return false; }
    if (senha.value.length < 6)       { showToast('Senha com mínimo 6 caracteres', 'warning'); senha.focus(); return false; }
    return true;
}

// ========================================
// DEBOUNCE
// ========================================

function debounce(func, wait = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ========================================
// INICIALIZAÇÃO
// ========================================

document.addEventListener('DOMContentLoaded', function () {

    // Token box — copiar ao clicar
    const tokenBox = document.querySelector('.token-box');
    if (tokenBox) {
        tokenBox.style.cursor = 'pointer';
        tokenBox.title = 'Clique para copiar';
        tokenBox.addEventListener('click', () => copiarToken(tokenBox.textContent.trim()));
    }

    // Confirmar logout
    const btnLogout = document.querySelector('.btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', e => {
            if (!confirm('Deseja realmente sair?')) e.preventDefault();
        });
    }

    // Validação login
    const loginForm = document.querySelector('form');
    if (loginForm && document.body.classList.contains('login-body')) {
        loginForm.addEventListener('submit', function (e) {
            if (!validarLogin(this)) e.preventDefault();
        });
    }
});

// ========================================
// ESTILOS TOAST
// ========================================

const _toastStyle = document.createElement('style');
_toastStyle.textContent = `
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 14px 18px;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 10000;
    animation: toastIn .3s ease;
    max-width: 400px;
    font-size: 14px;
    color: #333;
}
.toast-success { border-left: 4px solid #4caf50; }
.toast-error   { border-left: 4px solid #f44336; }
.toast-warning { border-left: 4px solid #ff9800; }
.toast-info    { border-left: 4px solid #2196f3; }
.toast-icon    { font-size: 18px; }
.toast-message { flex: 1; }
.toast-close   { background:none;border:none;font-size:20px;cursor:pointer;color:#999;padding:0;line-height:1; }
.toast-close:hover { color:#333; }
.toast-fadeout { animation: toastOut .3s ease; }
@keyframes toastIn  { from { transform:translateX(420px);opacity:0 } to { transform:translateX(0);opacity:1 } }
@keyframes toastOut { from { transform:translateX(0);opacity:1 } to { transform:translateX(420px);opacity:0 } }
@media(max-width:768px) {
    .toast-notification { top:10px;right:10px;left:10px;max-width:none; }
}
`;
document.head.appendChild(_toastStyle);
