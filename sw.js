// U2: PWA Service Worker - Connectix
const CACHE_NAME = 'connectix-v6-7-7';
const urlsToCache = [
  '/contax/assets/css/tailwind-compiled.css',
  '/contax/assets/css/fontawesome.min.css',
  '/contax/assets/css/vazirmatn.css',
  '/contax/assets/js/chart.min.js'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) return response;
        return fetch(event.request);
      })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
