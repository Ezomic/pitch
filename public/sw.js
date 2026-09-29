// Caches Vite's hashed build assets and nothing else. Pages, Inertia responses and
// every other request go straight to the network, so nothing a signed-in user sees
// is ever served from this cache.
//
// The page registers this script as /sw.js?v=<build manifest hash>. A deploy
// changes the hash, which installs a new worker, and activating it deletes the
// previous build's cache.
const CACHE = `pitch-build-${new URL(self.location.href).searchParams.get('v')}`;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith('/build/')) {
        return;
    }

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.status === 200) {
                event.waitUntil(cache.put(request, response.clone()));
            }

            return response;
        }),
    );
});
