/**
 * PWA (public/sw.js, public/manifest.webmanifest):
 * - registers the service worker (production build only);
 * - [data-offline-page] links: pages to keep ready for the bus (client passes);
 * - [data-pwa-install]: "Installer l’application" button, shown when the
 *   browser offers installation; [data-pwa-ios-hint]: iPhone instructions;
 * - [data-logout] forms: offline copies (personal data) are erased first;
 * - <body data-user-id>: the same copies are erased when the account changes
 *   (other login, logout, session expired).
 */
const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

const show = (selector, visible = true) => document.querySelectorAll(selector).forEach((el) => {
    el.hidden = !visible;
});

function keepPagesOffline(worker) {
    const urls = [...document.querySelectorAll('a[data-offline-page]')].map((link) => link.href);

    if (worker && urls.length > 0) {
        worker.postMessage({ type: 'cache-pages', urls });
    }
}

if ('serviceWorker' in navigator && !import.meta.env.DEV) {
    window.addEventListener('load', async () => {
        try {
            await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            const registration = await navigator.serviceWorker.ready;
            keepPagesOffline(registration.active);
        } catch {
            // No service worker (private mode, old browser): the site works online.
        }
    });
}

// Installation ---------------------------------------------------------------------------
let installPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    show('[data-pwa-install]');
});

window.addEventListener('appinstalled', () => {
    installPrompt = null;
    show('[data-pwa-install]', false);
});

document.addEventListener('click', async (event) => {
    if (!event.target.closest?.('[data-pwa-install]') || !installPrompt) {
        return;
    }

    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
    show('[data-pwa-install]', false);
});

document.addEventListener('DOMContentLoaded', () => {
    const iOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    show('[data-pwa-ios-hint]', iOS && !standalone());
});

// Logout: erase offline copies before leaving -----------------------------------------
// Controller's list of the day and queue of scans (resources/js/controle/offline.js).
const OFFLINE_CONTROL_DB = 'controle-hors-ligne';

async function pendingOfflineScans() {
    // Never create the database just to count: only if it already exists.
    const known = typeof indexedDB?.databases === 'function' ? await indexedDB.databases() : [];
    if (!known.some((db) => db.name === OFFLINE_CONTROL_DB)) {
        return 0;
    }

    return new Promise((resolve) => {
        const open = indexedDB.open(OFFLINE_CONTROL_DB);
        open.onerror = () => resolve(0);
        open.onsuccess = () => {
            const db = open.result;
            try {
                const count = db.transaction('queue').objectStore('queue').count();
                count.onsuccess = () => { db.close(); resolve(count.result); };
                count.onerror = () => { db.close(); resolve(0); };
            } catch {
                db.close();
                resolve(0);
            }
        };
    });
}

const deleteOfflineControl = () => new Promise((resolve) => {
    const request = indexedDB.deleteDatabase(OFFLINE_CONTROL_DB);
    request.onsuccess = request.onerror = request.onblocked = () => resolve();
    setTimeout(resolve, 3000); // another tab keeps it open: do not block the logout
});

// Opens the offline control database only if it exists (never creates it:
// the scanner creates its stores itself). Resolves to work(db) or null.
const withOfflineControl = (work) => new Promise((resolve) => {
    if (!('indexedDB' in window)) {
        resolve(null);
        return;
    }

    const open = indexedDB.open(OFFLINE_CONTROL_DB);
    open.onupgradeneeded = () => open.transaction.abort(); // did not exist
    open.onerror = open.onblocked = () => resolve(null);
    open.onsuccess = async () => {
        const db = open.result;
        try {
            resolve(await work(db));
        } catch {
            resolve(null);
        } finally {
            db.close();
        }
    };
});

const idbResult = (idbRequest) => new Promise((resolve, reject) => {
    idbRequest.onsuccess = () => resolve(idbRequest.result);
    idbRequest.onerror = () => reject(idbRequest.error);
});

// Account change -------------------------------------------------------------------------
// Pages kept by the service worker and the controller's list of the day belong
// to one account. <body data-user-id> (empty when logged out or session expired)
// is compared with the account seen last: when it changes, they are erased.
// Scans made offline and not sent yet are kept when the session simply expired
// (the same controller sends them after logging in again); another account
// erases them too.
const LAST_USER_KEY = 'app:last-user-id';

function lastUser() {
    try {
        return window.localStorage.getItem(LAST_USER_KEY);
    } catch {
        return null; // storage blocked: nothing to compare with
    }
}

function rememberUser(id) {
    try {
        window.localStorage.setItem(LAST_USER_KEY, id);
    } catch {
        // storage blocked: nothing to do
    }
}

async function clearPageCopies() {
    navigator.serviceWorker?.controller?.postMessage({ type: 'clear' });
    try {
        const keys = await window.caches?.keys() ?? [];
        await Promise.all(keys.filter((key) => key.startsWith('pages-')).map((key) => window.caches.delete(key)));
    } catch {
        // no Cache API: nothing kept
    }
}

async function clearOfflineControlFor(current) {
    const state = await withOfflineControl(async (db) => {
        const tx = db.transaction(['meta', 'queue'], 'readonly');
        const [owner, pending] = await Promise.all([idbResult(tx.objectStore('meta').get('owner')), idbResult(tx.objectStore('queue').count())]);

        return { owner: owner === undefined || owner === null ? null : String(owner), pending };
    });

    if (state === null || (current !== '' && state.owner === current)) {
        return; // nothing stored, or the same account logging in again
    }

    if (current === '' && state.pending > 0) {
        // Session expired with scans not sent yet: only the list of the day (names) goes.
        await withOfflineControl((db) => idbResult(db.transaction('meta', 'readwrite').objectStore('meta').delete('manifest')));

        return;
    }

    await (window.__clearOfflineControl?.() ?? deleteOfflineControl()).catch(() => {});
}

async function checkAccountChange() {
    const current = document.body?.dataset.userId;
    if (current === undefined) {
        return; // offline page, error outside a session: account unknown
    }

    const previous = lastUser();
    rememberUser(current);

    if (previous === null || previous === current) {
        return;
    }

    await clearPageCopies();
    await clearOfflineControlFor(current);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => checkAccountChange());
} else {
    checkAccountChange();
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches?.('[data-logout]') || form.dataset.cleared) {
        return;
    }

    event.preventDefault();

    const pending = await pendingOfflineScans().catch(() => 0);
    if (pending > 0 && !window.confirm(`${pending} scan(s) fait(s) hors ligne ne sont pas encore envoyés. Ils seront perdus si vous vous déconnectez maintenant.\n\nSe déconnecter quand même ?`)) {
        return;
    }

    navigator.serviceWorker?.controller?.postMessage({ type: 'clear' });
    window.dispatchEvent(new CustomEvent('app:logout'));
    await (window.__clearOfflineControl?.() ?? deleteOfflineControl()).catch(() => {});

    form.dataset.cleared = '1';
    form.submit();
});
