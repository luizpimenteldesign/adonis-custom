const user = verificarAuth();

document.addEventListener('DOMContentLoaded', async function() {
    if (user) {
        await carregarPreOS();
    }
});

async function carregarPreOS() {
    try {
        const response = await api.listarPreOS();
        const container = document.getElementById('osLista');

        if (response.success && response.data.length > 0) {
            container.innerHTML = response.data.map(os => \`
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">\${os.numero_pre_os}</h5>
                            <p class="card-text">
                                <strong>Cliente:</strong> \${os.cliente_nome}<br>
                                <strong>Telefone:</strong> \${os.cliente_telefone}<br>
                                <strong>Status:</strong> <span class="badge bg-primary">\${os.status}</span>
                            </p>
                            <small class="text-muted">\${new Date(os.created_at).toLocaleDateString('pt-BR')}</small>
                        </div>
                    </div>
                </div>
            \`).join('');
        } else {
            container.innerHTML = '<div class="col-12"><p class="text-center text-muted">Nenhuma Pré-OS encontrada</p></div>';
        }
    } catch (error) {
        console.error('Erro:', error);
        document.getElementById('osLista').innerHTML = '<div class="col-12"><p class="text-center text-danger">Erro ao carregar Pré-OS</p></div>';
    }
}
