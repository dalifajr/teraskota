const CACHE_NAME = 'teraskota-pos-v2';

const STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/offline.html',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/assets/css/custom.css',
    '/assets/js/offline-db.js',
    '/assets/js/sync-manager.js',
    '/assets/js/receipt.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

// Install: Cache critical assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            // Use allSettled approach so one failing optional CDN asset doesn't fail SW installation
            return Promise.allSettled(
                STATIC_ASSETS.map((url) => {
                    return fetch(url, { mode: 'cors' })
                        .then((res) => {
                            if (res.ok || res.type === 'opaque') {
                                return cache.put(url, res);
                            }
                        })
                        .catch(() => {
                            // Fallback for relative paths
                            return cache.add(url).catch(() => {});
                        });
                })
            );
        })
    );
    self.skipWaiting();
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch handling
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Do not intercept non-GET requests (e.g., checkout/sync POSTs)
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Navigation requests (HTML pages)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    // Try to match cached page (especially /pos)
                    const cachedResponse = await caches.match(request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Also try matching '/pos' explicitly if navigated to root or pos
                    if (url.pathname === '/pos' || url.pathname === '/') {
                        const posCached = await caches.match('/pos');
                        if (posCached) return posCached;
                    }
                    // Fallback to offline page
                    return caches.match('/offline.html');
                })
        );
        return;
    }

    // Static assets & CDN (CSS, JS, Fonts, Images)
    if (
        STATIC_ASSETS.includes(request.url) ||
        url.origin === location.origin && (
            url.pathname.startsWith('/assets/') ||
            url.pathname.startsWith('/icons/') ||
            url.pathname.endsWith('.css') ||
            url.pathname.endsWith('.js') ||
            url.pathname.endsWith('.png') ||
            url.pathname.endsWith('.jpg') ||
            url.pathname.endsWith('.svg') ||
            url.pathname.endsWith('.woff2')
        ) ||
        url.hostname.includes('cdn.jsdelivr.net') ||
        url.hostname.includes('cdnjs.cloudflare.com') ||
        url.hostname.includes('fonts.googleapis.com') ||
        url.hostname.includes('fonts.gstatic.com')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Return cached asset, revalidate in background if online
                    fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }

                return fetch(request).then((networkResponse) => {
                    if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
                        const clone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return networkResponse;
                }).catch(() => {
                    // Asset unavailable offline and not in cache
                });
            })
        );
        return;
    }

    // Default: network with cache fallback
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});