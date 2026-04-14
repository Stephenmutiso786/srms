const CACHE_NAME = "kyandulu-school-v3";
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
