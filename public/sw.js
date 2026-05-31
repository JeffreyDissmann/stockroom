/**
 * Stockroom service worker.
 *
 * Two cache layers:
 *   - APP_SHELL: app HTML/CSS/JS + manifest + icons. Cached on activate,
 *     served cache-first. Lets the app boot offline.
 *   - LAST_ITEMS: a small LRU of recent /items/{id} pages + their image
 *     responses. Stale-while-revalidate: instant offline, refreshed on
 *     next online visit.
 *
 * We deliberately do NOT cache POST/PATCH/DELETE responses, the search
 * API, the Inertia X-Inertia partials, or anything under /webhooks.
 * Authoritative writes belong to the network.
 *
 * Cache version: bumping APP_SHELL_VERSION invalidates the app shell on
 * deploy. Vite cache-busts its own assets, so the shell cache only needs
 * a manual bump when this file or the icon set changes shape.
 */

const APP_SHELL_VERSION = 'v1';
const APP_SHELL = `stockroom-shell-${APP_SHELL_VERSION}`;
const LAST_ITEMS = 'stockroom-items';
const LAST_ITEMS_MAX = 30;

const APP_SHELL_URLS = [
    '/manifest.webmanifest',
    '/icon.svg',
    '/icon-192.png',
    '/icon-512.png',
    '/favicon.ico',
    '/favicon-16.png',
    '/favicon-32.png',
    '/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(APP_SHELL).then((cache) => cache.addAll(APP_SHELL_URLS)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k !== APP_SHELL && k !== LAST_ITEMS)
                    .map((k) => caches.delete(k)),
            ),
        ),
    );
    self.clients.claim();
});

/**
 * Trim the LAST_ITEMS cache to LAST_ITEMS_MAX entries, dropping oldest first.
 * `Cache.keys()` returns insertion order, so we slice from the front.
 */
async function trimLastItems() {
    const cache = await caches.open(LAST_ITEMS);
    const keys = await cache.keys();
    const excess = keys.length - LAST_ITEMS_MAX;
    if (excess > 0) {
        await Promise.all(keys.slice(0, excess).map((req) => cache.delete(req)));
    }
}

/**
 * Stale-while-revalidate. If the cache has it, serve it immediately and
 * kick off a background refresh; otherwise wait for the network and cache
 * the result.
 */
async function staleWhileRevalidate(cacheName, request) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    const networkPromise = fetch(request)
        .then((resp) => {
            // Only cache successful, basic responses — no opaque CDN
            // payloads, no 4xx/5xx HTML pages.
            if (resp.ok && resp.type === 'basic') {
                cache.put(request, resp.clone());
            }
            return resp;
        })
        .catch(() => cached); // network failed; cached is our only hope
    return cached || networkPromise;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Same-origin only.
    if (url.origin !== self.location.origin) return;

    // Never intercept Inertia partial requests — they're driven by
    // X-Inertia / X-Inertia-Version and need a live server round-trip to
    // detect a stale asset version.
    if (request.headers.get('X-Inertia')) return;

    // Webhook / API surfaces stay network-only.
    if (url.pathname.startsWith('/webhooks/') || url.pathname.startsWith('/assistant/')) return;

    // App shell: try cache first, fall back to network. Covers /build/*
    // (Vite assets) plus the static icons.
    if (
        url.pathname.startsWith('/build/') ||
        APP_SHELL_URLS.includes(url.pathname)
    ) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request)),
        );
        return;
    }

    // Item pages: cache last N for offline browsing.
    if (/^\/items\/\d+$/.test(url.pathname)) {
        event.respondWith(
            staleWhileRevalidate(LAST_ITEMS, request).finally(() => {
                trimLastItems();
            }),
        );
        return;
    }

    // Item images (storage paths under /storage/items/…).
    if (url.pathname.startsWith('/storage/items/')) {
        event.respondWith(staleWhileRevalidate(LAST_ITEMS, request));
        return;
    }

    // Everything else: network-only. Inertia handles its own optimistic UX.
});
