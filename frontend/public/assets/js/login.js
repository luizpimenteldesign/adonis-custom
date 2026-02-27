document.getElementById('formLogin').addEventListener('submit', async function(e) {
    e.preventDefault();

    const email = document.getElementById('email').value;
    const senha = document.getElementById('senha').value;

    const btnLogin = document.getElementById('btnLogin');
    btnLogin.disabled = true;
    btnLogin.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Entrando...';

    try {
        const response = await api.login(email, senha);

        if (response.success) {
            localStorage.setItem('token', response.data.token);
            localStorage.setItem('user', JSON.stringify(response.data));
            window.location.href = 'dashboard.html';
        }
    } catch (error) {
        showAlert('Erro ao fazer login: ' + error.message, 'danger');
        btnLogin.disabled = false;
        btnLogin.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Entrar';
    }
});

function showAlert(message, type = 'info') {
    const container = document.getElementById('alert-container');
    container.innerHTML = \`
        <div class="alert alert-\${type} alert-dismissible fade show" role="alert">
            \${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    \`;
}
