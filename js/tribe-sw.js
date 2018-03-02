// CACHE_NAME is inserted via PHP.

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function (cache) {
        return fetch('tribe-files-to-cache.json')
          .then(function (response) {
            return response.json()
          }).then(function (files) {
            return cache.addAll(files)
          })
      }).then(function () {
      return self.skipWaiting()
    })
  )
})

self.addEventListener('fetch', function (event) {
  event.respondWith(
    caches.match(event.request, {
      ignoreSearch: true,
    }).then(function (response) {
      return response || fetch(event.request)
    })
  )
})

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim())
})
