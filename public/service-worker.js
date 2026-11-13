const CACHE_NAME = 'utilityflow-shell-v2';
const APP_SHELL = ['/login', '/manifest.webmanifest', '/water-reader.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (url.pathname === '/login' && request.mode === 'navigate') {
        event.respondWith((async () => {
            const cache = await caches.open(CACHE_NAME);
            const waterPages = (await cache.keys()).filter((cachedRequest) => new URL(cachedRequest.url).pathname === '/water-reading');
            await Promise.all(waterPages.map((cachedRequest) => cache.delete(cachedRequest)));
            try {
                return await fetch(request);
            } catch {
                return caches.match('/login');
            }
        })());
        return;
    }

    if (url.pathname === '/dashboard' && request.mode === 'navigate') {
        event.waitUntil(caches.open(CACHE_NAME).then(async (cache) => {
            const waterPages = (await cache.keys()).filter((cachedRequest) => new URL(cachedRequest.url).pathname === '/water-reading');
            await Promise.all(waterPages.map((cachedRequest) => cache.delete(cachedRequest)));
        }));
    }

    if (url.pathname === '/water-reading' && request.mode === 'navigate') {
        event.respondWith(fetch(request).then(async (response) => {
            if (response.ok && new URL(response.url).pathname === '/water-reading') {
                const cache = await caches.open(CACHE_NAME);
                await cache.put(request, response.clone());
            }
            return response;
        }).catch(async () => {
            const exactPage = await caches.match(request);
            if (exactPage) return exactPage;
            const cache = await caches.open(CACHE_NAME);
            const cachedWaterPages = (await cache.keys()).filter((cachedRequest) => new URL(cachedRequest.url).pathname === '/water-reading').reverse();
            const propertyPage = cachedWaterPages.find((cachedRequest) => new URL(cachedRequest.url).searchParams.has('property_id'));
            return (propertyPage && await cache.match(propertyPage)) || (await cache.match('/water-reading')) || caches.match('/login');
        }));
        return;
    }

    if (['script', 'style', 'font', 'image'].includes(request.destination)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then(async (response) => {
            if (response.ok) {
                const cache = await caches.open(CACHE_NAME);
                await cache.put(request, response.clone());
            }
            return response;
        })));
        return;
    }

    if (url.pathname.startsWith('/workspace/')) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => {
            const cached = await caches.match(request);
            return cached || caches.match('/login');
        }));
    }
});
