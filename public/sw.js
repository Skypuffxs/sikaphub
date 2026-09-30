/**
 * S.I.K.A.P. Hub - Service Worker
 * Provides offline support, caching, and PWA capabilities.
 */

const CACHE_NAME = 'sikaphub-v2';
const IS_SUBFOLDER = self.location.pathname.startsWith('/sikaphub');
const BASE_PREFIX = IS_SUBFOLDER ? '/sikaphub' : '';

const OFFLINE_URL = BASE_PREFIX + '/offline';

// Core assets required for offline availability
const PRECACHE_ASSETS = [
    BASE_PREFIX + '/',
    BASE_PREFIX + '/offline',
    BASE_PREFIX + '/assets/css/theme.css',
    BASE_PREFIX + '/assets/css/tom-select.css',
    BASE_PREFIX + '/assets/js/offline-handler.js',
    BASE_PREFIX + '/assets/js/tailwind.js',
    BASE_PREFIX + '/assets/js/tom-select.js',
    BASE_PREFIX + '/assets/images/logo-icon.png',
    BASE_PREFIX + '/manifest.json'
];



// Install Event: Pre-cache essential shell and offline fallback
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Pre-caching core application shell & offline assets');
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate Event: Clean up stale caches and take control immediately
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting obsolete cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event: Network-first for pages with offline fallback, Cache-first for static assets
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only intercept GET requests
    if (request.method !== 'GET') return;

    // Handle HTML Navigation requests
    if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    // Update cache with latest page if valid
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    // Try returning cached copy of requested URL
                    const cachedResponse = await caches.match(request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Fall back to offline page if not cached
                    const offlinePage = await caches.match(OFFLINE_URL);
                    return offlinePage || new Response('Offline - No connection', {
                        status: 503,
                        headers: { 'Content-Type': 'text/html' }
                    });
                })
        );
        return;
    }

    // Handle Static Assets (CSS, JS, Images, Fonts)
    if (
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font' ||
        url.pathname.includes('/public/assets/')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    // Serve cached asset immediately, update cache in background
                    fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, networkResponse));
                        }
                    }).catch(() => { /* Silent background network error */ });
                    return cachedResponse;
                }

                // If not in cache, fetch from network and cache it
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }
});
