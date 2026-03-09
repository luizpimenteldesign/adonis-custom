/**
 * SERVICE WORKER - ADONIS ADMIN PWA
 * Versão: 1.3
 * Fix: POST bloqueado, auto-atualização forçada via skipWaiting + clients.claim
 */

const CACHE_VERSION = 'adonis-admin-v3';
const urlsToCache = [
  '/backend/admin/dashboard.php',
  '/backend/admin/assets/css/admin.css',
  '/backend/admin/assets/css/sidebar.css',
  '/backend/admin/assets/css/pages.css',
  '/backend/admin/assets/js/admin.js',
  '/backend/admin/assets/js/sidebar.js',
  'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
  'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap'
];

/**
 * Verifica se a requisição pode ser cacheada.
 * Cache API só aceita GET e HEAD.
 */
function isCacheable(request) {
  if (request.method !== 'GET' && request.method !== 'HEAD') return false;
  if (!request.url.startsWith('http')) return false;
  if (request.headers.get('range')) return false;
  // Não cacheia URLs dinâmicas com parâmetros de ação (APIs)
  const url = new URL(request.url);
  if (url.searchParams.has('action')) return false;
  if (url.searchParams.has('id') && url.pathname.includes('-api')) return false;
  return true;
}

// ── Instalação ────────────────────────────────────────────────
self.addEventListener('install', event => {
  console.log('[SW Adonis] Instalando', CACHE_VERSION);
  // skipWaiting força o novo SW a assumir imediatamente,
  // substituindo qualquer versão antiga ainda ativa
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_VERSION)
      .then(cache => cache.addAll(urlsToCache))
      .catch(err => console.warn('[SW Adonis] Erro no pré-cache:', err))
  );
});

// ── Ativação ─────────────────────────────────────────────────
self.addEventListener('activate', event => {
  console.log('[SW Adonis] Ativando', CACHE_VERSION);
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(k => k.startsWith('adonis-admin') && k !== CACHE_VERSION)
          .map(k => {
            console.log('[SW Adonis] Removendo cache antigo:', k);
            return caches.delete(k);
          })
      )
    )
  );
  // Assume controle de todas as abas imediatamente
  return self.clients.claim();
});

// ── Fetch — Network First ─────────────────────────────────────
self.addEventListener('fetch', event => {
  // POST / PUT / DELETE: nunca interceptar, deixa passar direto
  if (!isCacheable(event.request)) return;

  // Ignora origens externas (exceto Google Fonts)
  const isExternal = !event.request.url.startsWith(self.location.origin);
  const isFonts    = event.request.url.includes('fonts.googleapis.com') ||
                     event.request.url.includes('fonts.gstatic.com');
  if (isExternal && !isFonts) return;

  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Cacheia apenas respostas válidas
        if (
          response &&
          response.status === 200 &&
          (response.type === 'basic' || response.type === 'cors')
        ) {
          const clone = response.clone();
          caches.open(CACHE_VERSION).then(cache => {
            // Dupla verificação: nunca cacheia POST (mesmo que chegue aqui)
            if (event.request.method === 'GET') {
              cache.put(event.request, clone);
            }
          });
        }
        return response;
      })
      .catch(() =>
        caches.match(event.request).then(cached =>
          cached ||
          new Response(
            '<html><body style="font-family:system-ui;text-align:center;padding:40px"><h1>\uD83D\uDEAB Sem conexão</h1><p>Verifique sua internet e tente novamente.</p></body></html>',
            { headers: { 'Content-Type': 'text/html' } }
          )
        )
      )
  );
});

console.log('[SW Adonis] v3 carregado — POST nunca cacheado');
