/**
 * SERVICE WORKER - ADONIS LUTHIERIA PWA
 * Versão: 1.0
 * Data: 07/03/2026
 */

const CACHE_NAME = 'adonis-orcamento-v1.0';
const urlsToCache = [
    '/frontend/index.php',
    '/frontend/public/acompanhar.php',
    '/frontend/public/assets/css/style.css',
    '/frontend/public/assets/js/form-luthieria.js',
    '/frontend/public/assets/img/Logo-Adonis3.png',
    '/frontend/public/assets/img/favicon.png',
    '/frontend/public/manifest.json'
];

// INSTALAÇÃO - Faz cache dos arquivos estáticos
self.addEventListener('install', function(event) {
    console.log('📦 Service Worker: Instalando...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('✅ Service Worker: Cache aberto');
                return cache.addAll(urlsToCache);
            })
            .then(function() {
                console.log('✅ Service Worker: Arquivos em cache');
                return self.skipWaiting();
            })
    );
});

// ATIVAÇÃO - Remove caches antigos
self.addEventListener('activate', function(event) {
    console.log('⚡ Service Worker: Ativando...');
    
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName !== CACHE_NAME) {
                        console.log('🗑️ Service Worker: Removendo cache antigo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(function() {
            console.log('✅ Service Worker: Ativado');
            return self.clients.claim();
        })
    );
});

// FETCH - Estratégia: Network First com Cache Fallback
self.addEventListener('fetch', function(event) {
    const url = new URL(event.request.url);
    
    // Ignora requisições que não são GET
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Ignora requisições para API (sempre precisa de dados frescos)
    if (url.pathname.includes('/backend/api/')) {
        return;
    }
    
    event.respondWith(
        fetch(event.request)
            .then(function(response) {
                // Se a resposta é válida, clona e guarda no cache
                if (response && response.status === 200) {
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME)
                        .then(function(cache) {
                            cache.put(event.request, responseToCache);
                        });
                }
                
                return response;
            })
            .catch(function() {
                // Se a rede falhar, tenta buscar do cache
                return caches.match(event.request)
                    .then(function(response) {
                        if (response) {
                            console.log('💾 Service Worker: Servindo do cache:', event.request.url);
                            return response;
                        }
                        
                        // Se não tem no cache, retorna página offline (opcional)
                        return new Response(
                            '<html><body><h1 style="text-align:center;margin-top:100px;font-family:sans-serif;">Sem conexão</h1><p style="text-align:center;color:#666;">Conecte-se à internet para usar o app.</p></body></html>',
                            {
                                headers: { 'Content-Type': 'text/html' }
                            }
                        );
                    });
            })
    );
});

// MENSAGENS - Para futuras funcionalidades (notificações, sync, etc)
self.addEventListener('message', function(event) {
    console.log('📨 Service Worker: Mensagem recebida:', event.data);
    
    if (event.data.action === 'skipWaiting') {
        self.skipWaiting();
    }
});

console.log('✅ Service Worker carregado');
