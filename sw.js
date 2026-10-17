// Service Worker for Connectix PWA ULTRA v4.0.53 - OFFLINE SUPPORT + NO LOOP
const CACHE_NAME = 'connectix-ultra-v4-0-53';
const STATIC_CACHE = 'connectix-static-v4-0-53';
const OFFLINE_CACHE = 'connectix-offline-v4-0-53';
const urlsToCache = [
  '/assets/css/fontawesome.min.css',
  '/assets/css/vazirmatn.css',
  '/assets/js/tailwind.js',
  '/manifest.json',
  '/offline.html'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE).then(cache => cache.addAll(urlsToCache)).catch(()=>{})
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.origin !== location.origin) return;
  
  // API, sub, dynamic pages - network-first, no cache HTML
  if (url.pathname.startsWith('/api/') || 
      url.pathname.startsWith('/sub/') || 
      url.pathname.includes('monitor') ||
      url.pathname.includes('dashboard') ||
      url.pathname === '/' ||
      url.pathname.startsWith('/clients') ||
      url.pathname.startsWith('/servers') ||
      url.pathname.startsWith('/resellers') ||
      url.pathname.startsWith('/billing') ||
      url.pathname.startsWith('/tickets') ||
      url.pathname.startsWith('/settings') ||
      url.pathname.startsWith('/plans') ||
      url.pathname.startsWith('/financial')) {
    event.respondWith(
      fetch(event.request)
        .then(response => response)
        .catch(() => {
          // If offline and requesting HTML, return offline page
          if (event.request.headers.get('accept')?.includes('text/html')) {
            return caches.match('/offline.html').then(cached => {
              if (cached) return cached;
              return new Response('<html><body style="background:#020617;color:white;text-align:center;padding:50px;font-family:sans-serif"><h1>📡 آفلاین</h1><p>اتصال اینترنت برقرار نیست</p><button onclick="location.reload()" style="background:#7c3aed;color:white;padding:10px 20px;border:none;border-radius:10px;margin-top:20px">تلاش مجدد</button></body></html>', {headers:{'Content-Type':'text/html'}});
            });
          }
          return caches.match(event.request);
        })
    );
    return;
  }
  
  // Static assets - cache-first with background update (stale-while-revalidate)
  if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff2?|ttf|ico|json)$/)) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        const fetchPromise = fetch(event.request).then(response => {
          if (response.ok) {
            caches.open(STATIC_CACHE).then(cache => {
              cache.put(event.request, response.clone());
            });
          }
          return response;
        }).catch(()=> cached);
        
        return cached || fetchPromise;
      })
    );
    return;
  }
  
  // Default: network-first with offline fallback
  event.respondWith(
    fetch(event.request)
      .then(r => r)
      .catch(() => caches.match(event.request).then(c => c || caches.match('/offline.html')))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    Promise.all([
      self.clients.claim(),
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.filter(name => name !== CACHE_NAME && name !== STATIC_CACHE && name !== OFFLINE_CACHE).map(name => {
            console.log('Deleting old cache:', name);
            return caches.delete(name);
          })
        );
      })
    ])
  );
});

// Background sync for offline actions (future)
self.addEventListener('sync', event => {
  if (event.tag === 'sync-clients') {
    event.waitUntil(
      // Will sync queued client creations when online
      Promise.resolve()
    );
  }
});
