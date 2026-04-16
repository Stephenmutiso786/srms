const CACHE_NAME = "kyandulu-school-v4";
const urlsToCache = [
  "./",
  "./school_main_website.php",
  "./index.php",
  "./css/main.css",
  "./js/main.js",
  "./js/bootstrap.min.js",
  "./js/jquery-3.7.0.min.js",
  "./images/pwa/icon-192.png",
  "./images/pwa/icon-512.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(urlsToCache)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  const requestUrl = new URL(event.request.url);
  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  const pathname = requestUrl.pathname || "";
  const accepts = event.request.headers.get("accept") || "";
  const isDynamicEndpoint =
    pathname.endsWith(".php") ||
    pathname.indexOf("/core/") !== -1 ||
    pathname.indexOf("/api/") !== -1 ||
    accepts.indexOf("application/json") !== -1;

  // Always prefer fresh HTML so UI updates are visible without hard refresh.
  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).then((response) => {
        if (response && response.status === 200 && response.type === "basic") {
          const cloned = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, cloned));
        }
        return response;
      }).catch(() => caches.match(event.request).then((cached) => cached || caches.match("./index.php")))
    );
    return;
  }

  // Dynamic endpoints must stay live; never serve stale cached API/auth data.
  if (isDynamicEndpoint) {
    event.respondWith(
      fetch(event.request).catch(() => {
        if (accepts.indexOf("application/json") !== -1) {
          return new Response(JSON.stringify({ ok: false, offline: true, message: "Offline" }), {
            status: 503,
            headers: { "Content-Type": "application/json" }
          });
        }
        return caches.match(event.request).then((cached) => cached || caches.match("./index.php"));
      })
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(event.request).then((response) => {
        if (!response || response.status !== 200 || response.type !== "basic") {
          return response;
        }
        const cloned = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, cloned));
        return response;
      }).catch(() => caches.match("./index.php"));
    })
  );
});

self.addEventListener("push", (event) => {
  const body = event.data ? event.data.text() : "New update available";
  event.waitUntil(
    self.registration.showNotification("Kyandulu Primary School", {
      body,
      icon: "./images/pwa/icon-192.png",
      badge: "./images/pwa/icon-192.png"
    })
  );
});
