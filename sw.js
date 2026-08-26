const CACHE_NAME = "clevedon-brewery-pwa-v2";
const APP_FILES = ["./","index.html","guide.html","style.css","app.js","manifest.webmanifest","assets/icon-192.png","assets/icon-512.png","assets/brewery-logo.svg","assets/ipa.svg","assets/bs21.svg","assets/foolsgold.jpg"];

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
            .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", event => {
    if (event.request.method !== "GET") return;
    event.respondWith(
        fetch(event.request, { cache: "no-store" })
            .then(response => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy)).catch(() => {});
                }
                return response;
            })
            .catch(() => caches.match(event.request).then(cached => cached || caches.match("index.html")))
    );
});
