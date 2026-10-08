// Pass checks without network on the bus (step 7).
//
// - The list of the passes valid today ("manifest", GET /api/controle/manifeste)
//   is kept in IndexedDB: no photo, no phone number, no signing key.
// - Offline, a scanned QR code (TT1.<t|s>.<uuid>.<key>.<sig>) or a typed
//   receipt number is looked up in that list. The signature cannot be checked
//   here; the uuid is random, so a forged code matches nothing, and the server
//   checks the signature when the scans are synced.
// - A typed receipt number is accepted for subscriptions only (name to compare
//   with an ID card); a daily ticket number typed by hand is refused
//   ("manual_entry"): its signed QR code must be scanned.
// - Every offline scan is queued, then sent to POST /api/controle/scans/sync
//   once back online (idempotent: one client_id per scan). The server refuses
//   scans older than 24 h: they are dropped from the queue (dropStale()).
// - Logout: clearOfflineControl() (also window.__clearOfflineControl) deletes
//   the whole database.

export const DB_NAME = 'controle-hors-ligne';
const DB_VERSION = 1;
const TZ = 'Africa/Abidjan';
const BATCH_SIZE = 200;
export const MAX_AGE_MS = 24 * 60 * 60 * 1000;
export const MANUAL_TICKET_REASON = 'Ticket du jour : scannez le QR code du reçu (saisie du numéro non acceptée)';
export const REFRESH_MS = 15 * 60 * 1000;

const ACCEPTED_CODES = ['ok', 'photo_missing', 'photo_offline'];
const RECEIPT_PATTERN = /^(BA|TJ)-?(\d{4})-?(\d{1,6})$/;
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

// -- Tiny IndexedDB wrapper ----------------------------------------------------------

let dbPromise = null;

function openDb() {
    if (!('indexedDB' in window)) {
        return Promise.reject(new Error('IndexedDB indisponible'));
    }

    dbPromise ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('meta')) {
                db.createObjectStore('meta');
            }
            if (!db.objectStoreNames.contains('queue')) {
                db.createObjectStore('queue', { keyPath: 'client_id' });
            }
        };
        request.onsuccess = () => {
            const db = request.result;
            // Another tab deletes the database (logout): let it go.
            db.onversionchange = () => {
                db.close();
                dbPromise = null;
            };
            resolve(db);
        };
        request.onerror = () => {
            dbPromise = null;
            reject(request.error);
        };
    });

    return dbPromise;
}

async function run(storeName, mode, work) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const request = work(transaction.objectStore(storeName));
        let result;

        if (request) {
            request.onsuccess = () => {
                result = request.result;
            };
        }
        transaction.oncomplete = () => resolve(result);
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

const getMeta = (key) => run('meta', 'readonly', (store) => store.get(key));
const setMeta = (key, value) => run('meta', 'readwrite', (store) => store.put(value, key));
const deleteMeta = (key) => run('meta', 'readwrite', (store) => store.delete(key));

/**
 * Deletes every offline control data (manifest + queued scans) of this
 * browser. Call it at logout; resolves once done (or after 3 s at most).
 */
export async function clearOfflineControl() {
    if (!('indexedDB' in window)) {
        return;
    }

    const db = dbPromise ? await dbPromise.catch(() => null) : null;
    db?.close();
    dbPromise = null;
    window.dispatchEvent(new CustomEvent('controle:offline-cleared'));

    await new Promise((resolve) => {
        const request = indexedDB.deleteDatabase(DB_NAME);
        request.onsuccess = () => resolve();
        request.onerror = () => resolve();
        // Another tab still has it open: it closes on "versionchange".
        request.onblocked = () => setTimeout(resolve, 3000);
    });
}

window.__clearOfflineControl = clearOfflineControl;
window.addEventListener('app:logout', () => {
    clearOfflineControl();
});

// -- Dates (Africa/Abidjan) ----------------------------------------------------------

const dayFormat = new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' });
const timeFormat = new Intl.DateTimeFormat('fr-FR', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });

export const todayIn = (date = new Date()) => dayFormat.format(date);
export const timeIn = (date = new Date()) => timeFormat.format(date);

const minutesOf = (hhmm) => {
    const [h, m] = String(hhmm).split(':').map(Number);

    return (h || 0) * 60 + (m || 0);
};

/** Same rule as VerifyPass::periodAt(). */
export function periodAt(date, eveningStartsAt) {
    return minutesOf(timeIn(date)) < minutesOf(eveningStartsAt ?? '12:00') ? 'morning' : 'evening';
}

// -- Manifest ------------------------------------------------------------------------

/**
 * Stored manifest, or null. A list of another day is dropped (queued scans
 * are kept until they are sent).
 */
export async function loadManifest() {
    const manifest = await getMeta('manifest');

    if (manifest && manifest.valid_for !== todayIn()) {
        await deleteMeta('manifest');

        return null;
    }

    return manifest ?? null;
}

/** User the stored data belongs to (null when none). */
export const storedOwner = () => getMeta('owner');

export async function downloadManifest(url) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const manifest = await response.json();
    if (!Array.isArray(manifest?.passes) || !manifest.valid_for) {
        throw new Error('Liste invalide');
    }

    manifest.stored_at = new Date().toISOString();
    await setMeta('owner', manifest.user_id);
    await setMeta('manifest', manifest);

    return manifest;
}

// -- Lookup and checks (same rules as App\Actions\Scans\VerifyPass) ------------------

/** "ba 2026 17" → BA-2026-0017 (subscriptions: 4 digits, tickets: 5). */
export function normalizeReceipt(input) {
    const compact = String(input).trim().toUpperCase().replace(/[\s_./]+/g, '-');
    const match = compact.match(RECEIPT_PATTERN);

    if (!match) {
        return compact;
    }

    return `${match[1]}-${match[2]}-${String(Number(match[3])).padStart(match[1] === 'BA' ? 4 : 5, '0')}`;
}

/** QR code → { kind, uuid }; anything else → { receipt }. */
export function parseCode(text) {
    const code = String(text ?? '').trim();

    if (code.startsWith('TT1.')) {
        const [, kind, uuid] = code.split('.');

        return (kind === 't' || kind === 's') && UUID_PATTERN.test(uuid ?? '') ? { kind, uuid: uuid.toLowerCase() } : { invalid: true };
    }

    return code === '' ? { invalid: true } : { receipt: normalizeReceipt(code) };
}

const indexes = new WeakMap();

function indexOf(manifest) {
    if (!indexes.has(manifest)) {
        const byKey = new Map();
        const byReceipt = new Map();

        for (const pass of manifest.passes) {
            byKey.set(`${pass.kind}:${pass.uuid}`, pass);
            if (pass.receipt_number) {
                byReceipt.set(pass.receipt_number, pass);
            }
        }
        indexes.set(manifest, { byKey, byReceipt });
    }

    return indexes.get(manifest);
}

function find(manifest, parsed) {
    const { byKey, byReceipt } = indexOf(manifest);

    if (parsed.uuid) {
        return byKey.get(`${parsed.kind}:${parsed.uuid}`) ?? null;
    }

    return parsed.receipt ? byReceipt.get(parsed.receipt) ?? null : null;
}

function details(pass, rides) {
    return [
        ['Formule', pass.formula],
        ['Zone', pass.zone],
        ['Montée', pass.boarding_point],
        ['Validité', pass.valid],
        ['Trajets', rides],
        ['Reçu', pass.receipt_number],
    ]
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([label, value]) => ({ label, value: String(value) }));
}

/**
 * Verdict for a code, offline. `queue` = scans already made offline (for the
 * anti-replay and the rides of a ticket).
 *
 * @returns {{status: string, reasonCode: string, reason: string, title: string, holder: ?string, details: Array, key: ?string, period: string}}
 */
export function checkOffline(manifest, queue, code, now = new Date()) {
    const period = periodAt(now, manifest.evening_starts_at);
    const parsed = parseCode(code);

    // Same rule as VerifyPass: a ticket number typed by hand is never accepted.
    if (parsed.receipt?.startsWith('TJ-')) {
        return { status: 'refused', reasonCode: 'manual_entry', reason: MANUAL_TICKET_REASON, title: 'Ticket du jour', holder: null, details: [], key: null, period };
    }

    const pass = find(manifest, parsed);

    if (!pass) {
        return { status: 'refused', reasonCode: 'unknown', reason: 'Code inconnu (vérification hors ligne)', title: 'Code inconnu', holder: null, details: [], key: null, period };
    }

    const key = `${pass.kind}:${pass.uuid}`;
    const isTicket = pass.kind === 't';
    const title = isTicket ? 'Ticket du jour' : `Abonnement ${pass.formula}`;
    const verdict = (status, reasonCode, reason, rides = null) => ({ status, reasonCode, reason, title, holder: pass.holder ?? null, details: details(pass, rides), key, period });

    if (!(pass.periods ?? []).includes(period)) {
        return verdict('refused', 'wrong_period', `Abonnement ${pass.formula} : non valable le ${period === 'morning' ? 'matin' : 'soir'}`);
    }

    // Anti-replay: scans queued on this phone, and the last accepted scan known
    // by the server (any controller) when the list was downloaded.
    const mine = queue.filter((item) => item.key === key);
    const windowStart = now.getTime() - (Number(manifest.anti_replay_minutes) || 0) * 60000;
    const previous = [
        ...mine.filter((item) => ACCEPTED_CODES.includes(item.reason_code)).map((item) => new Date(item.scanned_at)),
        ...(pass.last_accepted_at ? [new Date(pass.last_accepted_at)] : []),
    ]
        .filter((at) => at.getTime() >= windowStart && at.getTime() <= now.getTime())
        .sort((a, b) => b - a)[0];

    if (Number(manifest.anti_replay_minutes) > 0 && previous) {
        return verdict('warning', 'replay', `Déjà scanné à ${timeIn(previous)}`);
    }

    if (isTicket) {
        const used = Number(pass.rides_used) + mine.filter((item) => item.reason_code === 'ok').length;
        const allowed = Number(pass.rides_allowed);

        return used >= allowed
            ? verdict('refused', 'used_up', `Ticket déjà utilisé (${used}/${allowed} trajets)`, `${used}/${allowed}`)
            : verdict('valid', 'ok', `Trajet ${used + 1} sur ${allowed}`, `${used + 1}/${allowed}`);
    }

    const typed = parsed.receipt ? 'N° saisi à la main · ' : '';

    return pass.has_photo
        ? verdict('warning', 'photo_offline', `${typed}Photo non disponible hors ligne : vérifiez une pièce d’identité`)
        : verdict('warning', 'photo_missing', `${typed}Photo manquante : vérifiez une pièce d’identité`);
}

// -- Queue and sync ------------------------------------------------------------------

export async function allQueued() {
    const items = (await run('queue', 'readonly', (store) => store.getAll())) ?? [];

    return items.sort((a, b) => a.scanned_at.localeCompare(b.scanned_at));
}

export async function queueScan(code, check, now = new Date()) {
    const item = {
        client_id: crypto.randomUUID(),
        code: String(code).trim().slice(0, 200),
        key: check.key,
        result: check.status,
        reason_code: check.reasonCode,
        scanned_at: now.toISOString(),
        period: check.period,
    };
    await run('queue', 'readwrite', (store) => store.put(item));

    return item;
}

const removeQueued = (ids) => run('queue', 'readwrite', (store) => {
    ids.forEach((id) => store.delete(id));
});

/**
 * Removes the queued scans older than 24 h (the server refuses them).
 * Resolves to the number removed.
 */
export async function dropStale(now = Date.now()) {
    const stale = (await allQueued())
        .filter((item) => !(now - new Date(item.scanned_at).getTime() <= MAX_AGE_MS))
        .map((item) => item.client_id);

    if (stale.length > 0) {
        await removeQueued(stale);
    }

    return stale.length;
}

let syncing = null;

/**
 * Sends the queued scans (batches of 200), after dropping those older than
 * 24 h. Resolves to { sent, rejected, dropped, pending } — never throws (offline, session expired…:
 * the scans stay queued for the next try).
 */
export function syncQueue(url) {
    syncing ??= (async () => {
        let sent = 0;
        let rejected = 0;
        const dropped = await dropStale().catch(() => 0);

        try {
            for (;;) {
                const batch = (await allQueued()).slice(0, BATCH_SIZE);
                if (batch.length === 0) {
                    break;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        scans: batch.map(({ client_id, code, result, reason_code, scanned_at, period }) => ({ client_id, code, result, reason_code, scanned_at, period })),
                    }),
                });

                if (response.ok) {
                    const done = ((await response.json()).results ?? []).map((row) => row.client_id);
                    const ids = batch.map((item) => item.client_id).filter((id) => done.includes(id));
                    await removeQueued(ids);
                    sent += ids.length;
                    if (ids.length === 0) {
                        break;
                    }
                } else if (response.status === 422) {
                    // Scans the server will never accept (too old, clock far ahead…): dropped.
                    const errors = Object.keys((await response.json()).errors ?? {});
                    const bad = [...new Set(errors.map((field) => field.match(/^scans\.(\d+)\./)?.[1]).filter(Boolean))].map((index) => batch[Number(index)]?.client_id).filter(Boolean);
                    if (bad.length === 0) {
                        break;
                    }
                    await removeQueued(bad);
                    rejected += bad.length;
                } else {
                    break; // 401/419 (logged out), 429, 5xx: try again later.
                }
            }
        } catch {
            // Network gone again: try later.
        }

        const pending = (await allQueued().catch(() => [])).length;

        return { sent, rejected, dropped, pending };
    })().finally(() => {
        syncing = null;
    });

    return syncing;
}
