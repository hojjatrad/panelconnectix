// Service Worker for Connectix PWA ULTRA v7.0
const CACHE_NAME = 'connectix-ultra-v7-0';
const urlsToCache = [
  '/dashboard',
  '/assets/css/fontawesome.min.css',
  '/assets/css/vazirmatn.css',
  '/assets/js/tailwind.js'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache)).catch(()=>{})
  );
});

self.addEventListener('fetch', event => {
  // Only cache GET and same-origin
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.origin !== location.origin) return;
  // Don't cache API, sub, monitoring live
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/sub/') || url.pathname.includes('monitor')) return;
  
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request).catch(()=>{});
    })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(name => name !== CACHE_NAME).map(name => caches.delete(name))
      );
    })
  );
});
