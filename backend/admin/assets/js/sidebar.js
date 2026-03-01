/* sidebar.js — comportamento compartilhado da sidebar */

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

function toggleGroup(id) {
    const toggle = document.getElementById('toggle-' + id);
    const sub    = document.getElementById('sub-'    + id);
    if (!toggle || !sub) return;
    toggle.classList.toggle('open');
    sub.classList.toggle('open');
    // Persiste no localStorage
    const estado = JSON.parse(localStorage.getItem('adonis_nav') || '{}');
    estado[id]   = toggle.classList.contains('open');
    localStorage.setItem('adonis_nav', JSON.stringify(estado));
}

// Restaura estado salvo (sem sobrescrever grupos já abertos pelo PHP)
document.addEventListener('DOMContentLoaded', () => {
    const estado = JSON.parse(localStorage.getItem('adonis_nav') || '{}');
    for (const [id, aberto] of Object.entries(estado)) {
        const toggle = document.getElementById('toggle-' + id);
        const sub    = document.getElementById('sub-'    + id);
        if (!toggle || !sub) continue;
        if (aberto && !toggle.classList.contains('open')) {
            toggle.classList.add('open');
            sub.classList.add('open');
        }
    }
});
