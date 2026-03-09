/**
 * SERVICE WORKER - ADONIS ADMIN PWA
 * Versão: 1.1
 * Data: 09/03/2026
 * Fix: ignora métodos POST e requisições não cacheáveis (Cache API não suporta PUT em POST)
 */

const CACHE_NAME = 'adonis-admin-v2';
const urlsToCache = [
  '/backend/admin/dashboard.php',
  '/backend/admin/assets/css/admin.css',
  '/backend/admin/assets/css/sidebar.css',
  '/backend/admin/assets/css/dashboard.css',
  '/backend/admin/assets/js/admin.js',
  'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
  'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap'
];

// Verifica se a requisição pode ser cacheada
function isCacheable(request) {
  // Só cacheia GET e HEAD — POST, PUT, DELETE nunca são cacheáveis
  if (request.method !== 'GET' && request.method !== 'HEAD') return false;
  // Ignora chrome-extension e outros esquemas não-http
  if (!request.url.startsWith('http')) return false;
  // Ignora requisições com credenciais ou range headers (streams)
  if (request.headers.get('range')) return false;
  return true;
}

// Instalação
self.addEventListener('install', event => {
  console.log('[⚡ Admin SW] Instalando v2...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[⚡ Admin SW] Cache criado');
        return cache.addAll(urlsToCache);
      })
      .catch(err => {
        console.error('[⚡ Admin SW] Erro ao criar cache:', err);
      })
  );
  self.skipWaiting();
});

// Ativação
self.addEventListener('activate', event => {
  console.log('[⚡ Admin SW] Ativando v2...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME && cacheName.startsWith('adonis-admin')) {
            console.log('[⚡ Admin SW] Removendo cache antigo:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Fetch - Network First (admin precisa de dados atualizados)
self.addEventListener('fetch', event => {
  // POST e outros métodos não-GET: deixa passar direto, sem interceptar
  if (!isCacheable(event.request)) {
    return;
  }

  // Ignora requisições externas (exceto Google Fonts)
  if (!event.request.url.startsWith(self.location.origin) && !event.request.url.includes('fonts.googleapis.com')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Só cacheia respostas básicas/CORS válidas com status 200
        if (response && response.status === 200 && (response.type === 'basic' || response.type === 'cors')) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        // Se falhar, tenta buscar do cache
        return caches.match(event.request)
          .then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            return new Response(
              '<html><body style="font-family:system-ui;text-align:center;padding:40px"><h1>🚫 Sem conexão</h1><p>Verifique sua internet</p></body></html>',
              { headers: { 'Content-Type': 'text/html' } }
            );
          });
      })
  );
});

console.log('✅ Adonis Admin Service Worker v2 carregado');
