const API_URL = '/adonis-custom/backend/api';

class ApiService {
    async request(endpoint, options = {}) {
        const url = API_URL + endpoint;
        const config = {
            headers: { 'Content-Type': 'application/json' },
            ...options
        };

        const token = localStorage.getItem('token');
        if (token) {
            config.headers['Authorization'] = 'Bearer ' + token;
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Erro na requisição');
            }
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    async login(email, senha) {
        return this.request('/login.php', {
            method: 'POST',
            body: JSON.stringify({ email: email, senha: senha })
        });
    }

    async listarServicos() {
        return this.request('/servicos.php', { method: 'GET' });
    }

    async criarPreOS(dados) {
        return this.request('/pre-os.php', {
            method: 'POST',
            body: JSON.stringify(dados)
        });
    }

    async listarPreOS() {
        return this.request('/pre-os.php', { method: 'GET' });
    }
}

const api = new ApiService();
