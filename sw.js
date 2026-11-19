/* KitsuPlay service worker: offline shell + push notifications. */
const CACHE = "kitsuplay-v1";
const SHELL = ["/", "/index.php/offline", "/assets/css/app.css", "/assets/js/app.js", "/assets/icons/icon-192.png"];

self.addEventListener("install", (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).catch(() => undefined));
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))),
    );
    self.clients.claim();
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    if (request.method !== "GET") return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    // Playback, API and auth must never be served from a cache.
    if (
        url.pathname.startsWith("/api/") ||
        url.pathname.startsWith("/watch") ||
        url.pathname.startsWith("/auth") ||
        url.pathname.startsWith("/index.php/api")
    ) {
        return;
    }

    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match("/index.php/offline").then((hit) => hit || caches.match("/")),
            ),
        );
        return;
    }

    if (url.pathname.startsWith("/assets/")) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => undefined);
                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener("push", (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {};
    }
    const title = (data.notification && data.notification.title) || data.title || "KitsuPlay";
    const body = (data.notification && data.notification.body) || data.body || "A new announcement is live.";
    const url = (data.data && data.data.url) || "/index.php/notifications";
    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            icon: "/assets/icons/icon-192.png",
            badge: "/assets/icons/icon-64.png",
            data: { url },
            tag: "kitsuplay-announcement",
        }),
    );
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || "/index.php/notifications";
    event.waitUntil(
        clients.matchAll({ type: "window", includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (client.url.includes(self.location.origin)) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return clients.openWindow(target);
        }),
    );
});
