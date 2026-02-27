// Carrega header e footer dinamicamente
document.addEventListener('DOMContentLoaded', function() {
    carregarComponente('header-placeholder', '/adonis-custom/frontend/includes/header.html');
    carregarComponente('footer-placeholder', '/adonis-custom/frontend/includes/footer.html');
});

async function carregarComponente(elementId, url) {
    try {
        const response = await fetch(url);
        const html = await response.text();
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = html;
        }
    } catch (error) {
        console.error('Erro ao carregar componente:', error);
    }
}

// Customizar header conforme contexto
function customizarHeader(config) {
    setTimeout(() => {
        const subtitle = document.getElementById('header-subtitle');
        const actions = document.getElementById('header-actions');
        
        if (config.subtitle && subtitle) {
            subtitle.textContent = config.subtitle;
        }
        
        if (config.actions && actions) {
            actions.innerHTML = config.actions;
        }
    }, 100);
}
