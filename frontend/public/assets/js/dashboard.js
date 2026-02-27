const user = verificarAuth();

document.addEventListener('DOMContentLoaded', async function() {
    if (user) {
        document.getElementById('nomeUsuario').textContent = user.nome;
        await carregarDashboard();
    }
});

async function carregarDashboard() {
    try {
        const response = await api.listarPreOS();

        if (response.success) {
            const data = response.data;

            const stats = {
                aguardando: data.filter(p => p.status === 'aguardando_analise').length,
                em_analise: data.filter(p => p.status === 'em_analise').length,
                orcadas: data.filter(p => p.status === 'orcada').length,
                aprovadas: data.filter(p => p.status === 'aprovada').length
            };

            document.getElementById('statAguardando').textContent = stats.aguardando;
            document.getElementById('statEmAnalise').textContent = stats.em_analise;
            document.getElementById('statOrcadas').textContent = stats.orcadas;
            document.getElementById('statAprovadas').textContent = stats.aprovadas;

            const tbody = document.getElementById('tabelaUltimas');
            if (data.length > 0) {
                tbody.innerHTML = data.slice(0, 10).map(os => \`
                    <tr>
                        <td>\${os.numero_pre_os}</td>
                        <td>\${os.cliente_nome}</td>
                        <td>\${os.cliente_telefone}</td>
                        <td><span class="badge bg-primary">\${os.status}</span></td>
                        <td>\${new Date(os.created_at).toLocaleDateString('pt-BR')}</td>
                    </tr>
                \`).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhuma Pré-OS encontrada</td></tr>';
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
    }
}
