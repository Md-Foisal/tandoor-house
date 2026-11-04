/*
 * Staff app service worker (scope /staff/). It keeps the offline page and the
 * icons, nothing else: no staff page, Inertia response or API data is ever
 * cached. Reverb WebSockets are not fetches, so they are untouched.
 */
const VERSION = 'staff-v2';
const CACHE = `tandoor-staff-${VERSION}`;
const OFFLINE_URL = '/staff/offline';
const PRECACHE = [
    OFFLINE_URL,
    '/staff-app/icons/icon-192.png',
    '/staff-app/icons/icon-512.png',
    '/staff-app/icons/icon-maskable-512.png',
    '/staff-app/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('tandoor-staff-') &&
                                key !== CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches
                    .match(OFFLINE_URL)
                    .then((cached) => cached ?? Response.error()),
            ),
        );

        return;
    }

    if (PRECACHE.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request)),
        );
    }
});
