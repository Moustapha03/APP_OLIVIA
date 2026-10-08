/*
 * Service worker (PWA). Registered by resources/js/pwa.js, scope "/".
 *
 * - /build/* (hashed Vite files), /icons/*, the manifest: cache first.
 * - Pages: always from the network. A copy of the few pages useful on the
 *   bus is kept (client passes with their QR code, controller scanner) and
 *   shown when the network is gone; otherwise the "hors ligne" page.
 * - Never cached: forms (non-GET), /api, receipts and PDFs, admin, desk.
 * - At logout the page asks to clear the copies (personal data); the
 *   server also sends "Clear-Site-Data". The page also asks to clear them
 *   when the logged-in account changes (pwa.js, <body data-user-id>):
 *   copies are keyed by path only, never shown to another account.
 *
 * Bump VERSION when this file changes: old caches are removed on activate.
 */
const VERSION = 'v2';
const STATIC_CACHE = `static-${VERSION}`;
const PAGES_CACHE = `pages-${VERSION}`;
const OFFLINE_URL = '/hors-ligne';

const PRECACHE = [OFFLINE_URL, '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon.svg'];

// Pages kept for offline use (prefixes, without query string).
const OFFLINE_PAGES = [/^\/espace\/?$/, /^\/espace\/titres(\/.*)?$/, /^\/controle\/scanner$/, /^\/controle\/historique$/];
// Never cache these, even if they match the above (downloads).
const NEVER = [/\/pdf$/];

const isOfflinePage = (url) => OFFLINE_PAGES.some((re) => re.test(url.pathname)) && !NEVER.some((re) => re.test(url.pathname));
const isStatic = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest';

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll(PRECACHE);

        // The offline page needs its CSS/JS: cache the Vite files it links to.
        const html = await (await cache.match(OFFLINE_URL)).text();
        const assets = [...html.matchAll(/(?:href|src)="([^"]*\/build\/[^"]+)"/g)].map((m) => new URL(m[1], self.location.origin).pathname);
        await cache.addAll([...new Set(assets)]);

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = [STATIC_CACHE, PAGES_CACHE];
        await Promise.all((await caches.keys()).filter((key) => !keep.includes(key)).map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
        return;
    }

    if (isStatic(url)) {
        event.respondWith(cacheFirst(request));
    } else if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request, url));
    }
});

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (response.ok) {
        (await caches.open(STATIC_CACHE)).put(request, response.clone());
    }

    return response;
}

async function networkFirstPage(request, url) {
    try {
        const response = await fetch(request);

        // Keep a copy of a useful page, never of a redirect (e.g. to the login page).
        if (response.ok && !response.redirected && isOfflinePage(url)) {
            (await caches.open(PAGES_CACHE)).put(url.pathname, response.clone());
        }

        return response;
    } catch {
        const copy = await (await caches.open(PAGES_CACHE)).match(url.pathname);

        return copy || (await caches.match(OFFLINE_URL)) || Response.error();
    }
}

self.addEventListener('message', (event) => {
    const data = event.data || {};

    if (data.type === 'clear') {
        event.waitUntil(caches.delete(PAGES_CACHE));
    }

    // Pages to keep ready offline (e.g. each running pass of the client).
    if (data.type === 'cache-pages' && Array.isArray(data.urls)) {
        event.waitUntil((async () => {
            const cache = await caches.open(PAGES_CACHE);
            for (const href of data.urls.slice(0, 20)) {
                const url = new URL(href, self.location.origin);
                if (url.origin !== self.location.origin || !isOfflinePage(url)) {
                    continue;
                }
                try {
                    const response = await fetch(url.pathname, { credentials: 'same-origin' });
                    if (response.ok && !response.redirected) {
                        await cache.put(url.pathname, response);
                    }
                } catch {
                    // Offline: try again on the next visit.
                }
            }
        })());
    }
});
