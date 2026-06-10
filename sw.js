// sw.js - Service Worker for LX PWA
const CACHE_NAME = 'lx-cache-v2';
const ASSETS_TO_CACHE = [
  './index.php',
  './manifest.json',
  './assets/css/style.css?v=2',
  './assets/js/app.js?v=2',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png'
];

// Install Service Worker and cache essential files
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Caching app shell');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Service Worker and clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[Service Worker] Clearing old cache', cache);
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch events: Network first, then fall back to cache (useful for dynamic php backend)
// For static assets, we check cache first
self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  // Let index.php and API requests try network first, fallback to offline UI cache if failed
  const url = new URL(event.request.url);
  
  if (url.pathname.endsWith('index.php') || url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) return cachedResponse;
            // Fallback for document navigation if offline and not cached
            if (event.request.mode === 'navigate') {
              return caches.match('./index.php');
            }
            return Response.error();
          });
        })
    );
  } else {
    // For static files (CSS, JS, Fonts, Images), use cache-first strategy
    event.respondWith(
      caches.match(event.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const cacheCopy = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, cacheCopy);
            });
          }
          return networkResponse;
        }).catch(() => {
          // Fallback image if icons fail
          if (event.request.url.match(/\.(jpe?g|png|gif|svg)$/)) {
            return caches.match('./assets/icons/icon-192.png');
          }
        });
      })
    );
  }
});
