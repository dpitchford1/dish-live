/**
 * Service Worker
 * Provides asset caching for improved performance
 */

const CACHE_NAME = 'dish-asset-cache-v0.0.1';
const ASSETS_TO_CACHE = [
  // CSS files
  //'/assets/css/build/dish-base-layout.min.css',
  
  // Images
  '/assets/img/logos/dish.svg',
  '/favicon.ico',
//   '/assets/img/logos/dish-favicon-260x260.png',
//   '/assets/img/logos/dish-favicon-150x150.png',
  
  // Icons
  //'/favicon.ico',
//   '/assets/img/icon/safari-pinned-tab.svg',
//   '/assets/img/icon/favicon-32x32.png',
//   '/assets/img/icon/favicon-16x16.png',
//   '/assets/img/icon/apple-touch-icon.png',
  
// Add your font files here - examples:
  '/assets/fonts/poppins/poppins-light.woff2', 
  '/assets/fonts/brandon-grotesque/BrandonGrotesque-Regular.woff2', 

   // JS files
   //'/assets/js/core/base.min.js',
//    '/assets/js/resources/cookie-consent.min.js',
//    '/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js'
];

/**
 * Installation event
 * Caches all predefined assets when the service worker is installed
 */
self.addEventListener('install', event => {
  // Use waitUntil to signal the duration of the install event
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache opened, adding assets');
        return cache.addAll(ASSETS_TO_CACHE);
      })
      .catch(error => {
        console.error('Error during service worker install:', error);
      })
  );
  
  // Force this service worker to activate immediately if another version is waiting
  self.skipWaiting();
});

/**
 * Activation event
 * Cleans up old caches when a new service worker is activated
 */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(cacheName => {
          // Delete any caches that match our pattern but aren't the current version
          return cacheName.startsWith('mic-asset-cache-') && cacheName !== CACHE_NAME;
        }).map(cacheName => {
          console.log('Deleting old cache:', cacheName);
          return caches.delete(cacheName);
        })
      );
    })
  );
  
  // Ensure the service worker immediately takes control of the page
  return self.clients.claim();
});

/**
 * Fetch event
 * Serves cached assets when available, otherwise fetches from network
 * Only serves assets from the cache if they are in ASSETS_TO_CACHE
 */
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Only handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Get the pathname (relative URL) for comparison
  const url = new URL(event.request.url);
  const pathname = url.pathname;

  // Only handle requests that are in ASSETS_TO_CACHE
  if (ASSETS_TO_CACHE.includes(pathname)) {
    event.respondWith(
      caches.open(CACHE_NAME).then(cache => {
        return cache.match(event.request).then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // Not in cache, fetch from network (but do NOT cache it)
          return fetch(event.request);
        });
      })
    );
  }
  // If not in ASSETS_TO_CACHE, do nothing—browser/server handles it
});

/**
 * Push event - for future implementation of push notifications
 */
// self.addEventListener('push', event => {
//   // Handle push notifications here when you're ready to implement them
// });

/**
 * Message event - for communication with main thread
 */
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
