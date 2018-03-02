var tribe_sw = window.tribe_sw || {}

if ('serviceWorker' in navigator && tribe_sw.base) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(tribe_sw.base + 'tribe-sw.js')
      .then(function (registration) {
        console.log('ServiceWorker registration successful with scope: ', registration.scope)
      }, function (err) {
        console.log('ServiceWorker registration failed: ', err)
      })
  })
}
