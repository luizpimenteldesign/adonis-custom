/**
 * SERVICE WORKER - ADONIS ADMIN PWA
 * Versão: 1.0
 * Data: 07/03/2026
 */

const CACHE_NAME = 'adonis-admin-v1';
const urlsToCache = [
  '/backend/admin/dashboard.php',
  '/backend/admin/assets/css/admin.css',
  '/backend/admin/assets/css/sidebar.css',
  '/backend/admin/assets/css/dashboard.css',
  '/backend/admin/assets/js/admin.js',
  'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
  'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap'
];

// Instalação
self.addEventListener('install', event => {
  console.log('[⚡ Admin SW] Instalando...');
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
  console.log('[⚡ Admin SW] Ativando...');
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
  // Ignora requisições externas (exceto Google Fonts)
  if (!event.request.url.startsWith(self.location.origin) && !event.request.url.includes('fonts.googleapis.com')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Clona a resposta para salvar no cache
        const responseToCache = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, responseToCache);
        });
        return response;
      })
      .catch(() => {
        // Se falhar, tenta buscar do cache
        return caches.match(event.request)
          .then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // Se não tiver no cache, retorna página offline
            return new Response(
              '<html><body style="font-family:system-ui;text-align:center;padding:40px"><h1>🚫 Sem conexão</h1><p>Verifique sua internet</p></body></html>',
              { headers: { 'Content-Type': 'text/html' } }
            );
          });
      })
  );
});

console.log('✅ Adonis Admin Service Worker carregado');
