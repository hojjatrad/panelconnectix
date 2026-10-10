// Service Worker for Connectix PWA ULTRA v4.0.47 - FIXED: No infinite reload loop
const CACHE_NAME = 'connectix-ultra-v4-0-47';
const STATIC_CACHE = 'connectix-static-v4-0-47';
const urlsToCache = [
  '/assets/css/fontawesome.min.css',
  '/assets/css/vazirmatn.css',
  '/assets/js/tailwind.js'
];

self.addEventListener('install', event => {
  // Skip waiting to activate new SW immediately
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE).then(cache => cache.addAll(urlsToCache)).catch(()=>{})
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.origin !== location.origin) return;
  
  // NEVER cache API, sub, monitoring, dashboard HTML - always network-first
  if (url.pathname.startsWith('/api/') || 
      url.pathname.startsWith('/sub/') || 
      url.pathname.includes('monitor') ||
      url.pathname.includes('dashboard') ||
      url.pathname === '/' ||
      url.pathname.startsWith('/clients') ||
      url.pathname.startsWith('/servers') ||
      url.pathname.startsWith('/resellers') ||
      url.pathname.startsWith('/billing') ||
      url.pathname.startsWith('/tickets')) {
    // Network-first for HTML pages - FIX for Ctrl+F5 issue
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Don't cache HTML responses
          return response;
        })
        .catch(() => {
          // Fallback to cache only if network fails
          return caches.match(event.request);
        })
    );
    return;
  }
  
  // For static assets (CSS, JS, images, fonts) - cache-first with network fallback
  if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff2?|ttf|ico)$/)) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) {
          // Return cached but also update cache in background
          event.waitUntil(
            fetch(event.request).then(response => {
              return caches.open(STATIC_CACHE).then(cache => {
                cache.put(event.request, response.clone());
                return response;
              });
            }).catch(()=>{})
          );
          return cached;
        }
        // Not in cache, fetch and cache
        return fetch(event.request).then(response => {
          return caches.open(STATIC_CACHE).then(cache => {
            cache.put(event.request, response.clone());
            return response;
          });
        });
      })
    );
    return;
  }
  
  // Default: network-first
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});

self.addEventListener('activate', event => {
  // Claim clients immediately
  event.waitUntil(
    Promise.all([
      self.clients.claim(),
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.filter(name => name !== CACHE_NAME && name !== STATIC_CACHE).map(name => {
            console.log('Deleting old cache:', name);
            return caches.delete(name);
          })
        );
      })
    ])
  );
});
