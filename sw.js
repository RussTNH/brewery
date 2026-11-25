const CACHE_NAME = "clevedon-brewery-pwa-v3";
const APP_FILES = ["./","index.html","guide.html","style.css?v=12","app.js?v=5","manifest.webmanifest","assets/icon-192.png","assets/icon-512.png","assets/brewery-logo.svg","assets/ipa.svg","assets/bs21.svg","assets/foolsgold.jpg"];

self.addEventListener("install", event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(APP_FILES))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener("activate", event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys
                .filter(key => key.startsWith("clevedon-brewery-pwa-") && key !== CACHE_NAME)
                .map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", event => {
    if (event.request.method !== "GET") return;

    const url = new URL(event.request.url);
    const privatePath = /\/(?:admin)(?:\/|$)/.test(url.pathname)
        || /\/lib(?:\/|$)/.test(url.pathname)
        || /\/track\.php$/.test(url.pathname)
        || /\/live-data\/data\/stats\.(?:json|php)$/.test(url.pathname);

    // Admin pages and statistics must always go directly to the server and
    // must never be retained in a visitor's service-worker cache.
    if (url.origin !== self.location.origin || privatePath) return;

    event.respondWith(
        fetch(event.request, { cache: "no-store" })
            .then(response => {
                if (response.ok && response.type === "basic") {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy)).catch(() => {});
                }
                return response;
            })
            .catch(async () => {
                const cached = await caches.match(event.request);
                if (cached) return cached;
                if (event.request.mode === "navigate") {
                    return caches.match(url.pathname.endsWith("/guide.html") ? "guide.html" : "index.html");
                }
                return Response.error();
            })
    );
});
