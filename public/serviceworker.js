var staticCacheName = "pwa-v" + new Date().getTime();
var filesToCache = [
    '/offline',
    "/storage/01JD3XGFJVKBS5HA3Z06ZF8357.png",
    "/storage/01JD3XGFJVKBS5HA3Z06ZF8359.png",
    "/storage/01JD3XGFJWZYQRC2SBP8PG6GGR.png",
    "/storage/01JD3XGFJWZYQRC2SBP8PG6GGT.png",
    "/storage/01JD3XGFJWZYQRC2SBP8PG6GGW.png",
    "/storage/01JD3XGFJX9DSK2252P55NRJ7N.png",
    "/storage/01JD3XGFJX9DSK2252P55NRJ7Q.png",
    "/storage/01JD3XGFJX9DSK2252P55NRJ7S.png"
];

// Cache on install (skip missing assets so one 404 does not break the whole install)
self.addEventListener("install", event => {
    this.skipWaiting();
    event.waitUntil(
        caches.open(staticCacheName)
            .then(cache => Promise.allSettled(
                filesToCache.map(url => cache.add(url).catch(() => undefined))
            ))
    );
});

// Clear cache on activate
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => (cacheName.startsWith("pwa-")))
                    .filter(cacheName => (cacheName !== staticCacheName))
                    .map(cacheName => caches.delete(cacheName))
            );
        })
    );
});

// Serve from Cache
self.addEventListener("fetch", event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                return response || fetch(event.request);
            })
            .catch(() => {
                return caches.match('/offline');
            })
    );
});
