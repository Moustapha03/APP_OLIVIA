// Pass scanner (resources/views/controle/scan.blade.php).
//
// Uses the low-level Html5Qrcode class (no ready-made UI): the library only
// sets styles through element.style, which the CSP allows. It is loaded on
// demand, so other pages do not download it.
//
// Markup contract:
//   x-ref="camera"        empty box the video goes into (needs an id)
//   x-ref="form"          POST form to controle.scan.store, x-ref="code" its hidden input
//   x-ref="receipt"       the "N° de reçu" form (x-on:submit="submitReceipt"),
//   x-ref="receiptInput"  its text field
//   x-ref="next"          "Scanner le suivant" button of the offline verdict
//   data-user-id, data-manifest-url, data-sync-url, data-ping-url on the root
//
// Without network (navigator.onLine false, or /api/me unreachable), the code
// is checked against the list of the day kept on the phone (controle/offline.js)
// and the verdict is shown on this page; the scan is queued and sent to the
// server once back online.
import { allQueued, checkOffline, clearOfflineControl, downloadManifest, dropStale, loadManifest, queueScan, REFRESH_MS, storedOwner, syncQueue, timeIn } from '../controle/offline';

const ERROR_MESSAGES = {
    denied: 'Caméra refusée. Autorisez-la dans les réglages du navigateur (icône à gauche de l’adresse), puis touchez « Réessayer ».',
    missing: 'Aucune caméra trouvée sur cet appareil.',
    busy: 'La caméra est utilisée par une autre application. Fermez-la puis touchez « Réessayer ».',
    insecure: 'La caméra demande une connexion sécurisée (https).',
    other: 'Impossible de démarrer la caméra.',
};

// Same colours and words as <x-scan-result>.
const RESULT_STYLES = {
    valid: { classes: 'bg-success-600 text-white', label: 'VALIDE' },
    refused: { classes: 'bg-danger-600 text-white', label: 'REFUSÉ' },
    warning: { classes: 'bg-warning-400 text-slate-950', label: 'ATTENTION' },
};

const PING_TIMEOUT_MS = 4000;

function errorKind(error) {
    const text = `${error?.name ?? ''} ${error?.message ?? ''} ${error ?? ''}`;

    if (/NotAllowed|Permission|SecurityError/i.test(text)) {
        return 'denied';
    }
    if (/NotFound|DevicesNotFound|OverconstrainedError|no camera/i.test(text)) {
        return 'missing';
    }
    if (/NotReadable|TrackStart|Could not start/i.test(text)) {
        return 'busy';
    }

    return 'other';
}

export default () => ({
    // idle | starting | scanning | sending | error
    state: 'idle',
    message: '',
    scanner: null,

    // Offline mode
    manifest: null,
    pending: 0,
    dropped: 0,
    result: null,
    busy: false,

    get starting() {
        return this.state === 'starting';
    },

    get scanning() {
        return this.state === 'scanning';
    },

    get sending() {
        return this.state === 'sending' || this.busy;
    },

    get failed() {
        return this.state === 'error';
    },

    // Kept while sending: the frozen frame shows what was read.
    get cameraVisible() {
        return this.state === 'starting' || this.state === 'scanning' || this.state === 'sending';
    },

    // -- Offline verdict (getters only: Alpine CSP build) -------------------------

    get showingResult() {
        return this.result !== null;
    },

    get resultStatus() {
        return this.result?.status ?? 'refused';
    },

    get resultClass() {
        return (RESULT_STYLES[this.resultStatus] ?? RESULT_STYLES.refused).classes;
    },

    get resultLabel() {
        return (RESULT_STYLES[this.resultStatus] ?? RESULT_STYLES.refused).label;
    },

    get isValid() {
        return this.resultStatus === 'valid';
    },

    get isRefused() {
        return this.resultStatus === 'refused';
    },

    get isWarning() {
        return this.resultStatus === 'warning';
    },

    get resultBadge() {
        return this.result?.reasonCode === 'no_manifest' ? 'Hors ligne' : 'Hors ligne · vérifié sur la liste du jour';
    },

    get resultTitle() {
        return this.result?.title ?? '';
    },

    get resultReason() {
        return this.result?.reason ?? '';
    },

    get resultHolder() {
        return this.result?.holder ?? '';
    },

    get hasHolder() {
        return Boolean(this.result?.holder);
    },

    get resultRows() {
        return this.result?.details ?? [];
    },

    get hasRows() {
        return this.resultRows.length > 0;
    },

    // -- Status line --------------------------------------------------------------

    get manifestLabel() {
        return this.manifest
            ? `Liste du jour : à jour (${timeIn(new Date(this.manifest.stored_at ?? this.manifest.generated_at))})`
            : 'Liste du jour : non téléchargée';
    },

    get manifestReady() {
        return this.manifest !== null;
    },

    get manifestMissing() {
        return this.manifest === null;
    },

    get hasPending() {
        return this.pending > 0;
    },

    get hasDropped() {
        return this.dropped > 0;
    },

    // Scans the server would refuse (more than 24 h old): removed, the controller is told.
    get droppedLabel() {
        return this.dropped > 1
            ? `${this.dropped} scans hors ligne de plus de 24 h supprimés sans envoi (trop anciens)`
            : '1 scan hors ligne de plus de 24 h supprimé sans envoi (trop ancien)';
    },

    get pendingLabel() {
        return `${this.pending} scan${this.pending > 1 ? 's' : ''} en attente d’envoi`;
    },

    init() {
        this.onPageHide = () => this.stop();
        window.addEventListener('pagehide', this.onPageHide);
        this.start();
        this.setUpOffline();
    },

    destroy() {
        window.removeEventListener('pagehide', this.onPageHide);
        window.removeEventListener('online', this.onOnline);
        window.removeEventListener('controle:offline-cleared', this.onCleared);
        clearInterval(this.refreshTimer);
        this.stop();
    },

    async start() {
        if (this.state === 'starting' || this.state === 'scanning') {
            return;
        }

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            this.fail('insecure');
            return;
        }

        this.state = 'starting';
        this.message = '';

        try {
            const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');

            this.scanner ??= new Html5Qrcode(this.$refs.camera.id, {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                verbose: false,
            });

            await this.scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: (width, height) => this.frame(width, height) },
                (text) => this.decoded(text),
                () => {},
            );

            this.state = 'scanning';
        } catch (error) {
            this.scanner = null;
            this.fail(errorKind(error));
        }
    },

    async stop() {
        if (this.scanner?.isScanning) {
            try {
                await this.scanner.stop();
            } catch {
                // Already stopped: nothing to do.
            }
        }
    },

    // Square aiming frame: 70 % of the smallest side.
    frame(width, height) {
        const size = Math.max(160, Math.floor(Math.min(width, height) * 0.7));

        return { width: size, height: size };
    },

    decoded(text) {
        if (this.state !== 'scanning' || this.result !== null) {
            return;
        }

        this.state = 'sending';
        navigator.vibrate?.(80);
        this.scanner?.pause(true);

        return this.verify(text, () => {
            this.$refs.code.value = text;
            this.$refs.form.requestSubmit();
        });
    },

    fail(kind) {
        this.state = 'error';
        this.message = ERROR_MESSAGES[kind] ?? ERROR_MESSAGES.other;
    },

    retry() {
        this.start();
    },

    // -- Offline list and queue ---------------------------------------------------

    async setUpOffline() {
        const { userId, manifestUrl, syncUrl, pingUrl } = this.$el.dataset;
        this.userId = Number(userId);
        this.manifestUrl = manifestUrl;
        this.syncUrl = syncUrl;
        this.pingUrl = pingUrl;

        this.onOnline = () => this.whenOnline(true);
        this.onCleared = () => {
            this.manifest = null;
            this.pending = 0;
            this.dropped = 0;
        };
        window.addEventListener('online', this.onOnline);
        window.addEventListener('controle:offline-cleared', this.onCleared);
        this.refreshTimer = setInterval(() => this.whenOnline(true), REFRESH_MS);

        try {
            // Data left on this phone by another account: erased first.
            const owner = await storedOwner();
            if (owner !== undefined && owner !== null && Number(owner) !== this.userId) {
                await clearOfflineControl();
            }

            this.manifest = await loadManifest();
            this.dropped += await dropStale();
            await this.countPending();
        } catch {
            // No IndexedDB (private mode…): online checks still work.
            return;
        }

        await this.whenOnline(false);
    },

    async countPending() {
        this.pending = (await allQueued()).length;
    },

    // Sends the queued scans, then refreshes the list (forced, or older than 15 min).
    async whenOnline(force) {
        if (!navigator.onLine || !this.syncUrl) {
            return;
        }

        const { sent, rejected, dropped } = await syncQueue(this.syncUrl);
        this.dropped += rejected + dropped;
        await this.countPending().catch(() => {});

        const storedAt = this.manifest ? new Date(this.manifest.stored_at ?? 0).getTime() : 0;
        if (force || sent > 0 || !this.manifest || Date.now() - storedAt >= REFRESH_MS) {
            try {
                this.manifest = await downloadManifest(this.manifestUrl);
            } catch {
                // Network or session gone: keep the list we have.
            }
        }
    },

    // Is the server reachable? navigator.onLine stays true on a dead mobile connection.
    async reachable() {
        if (!navigator.onLine) {
            return false;
        }

        try {
            await fetch(this.pingUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(PING_TIMEOUT_MS),
            });

            return true; // Any answer (even 401) means the network works.
        } catch {
            return false;
        }
    },

    // Online: the form is sent as before (server verdict page).
    // Offline: verdict from the list of the day, scan queued.
    async verify(code, submitOnline) {
        if (await this.reachable()) {
            submitOnline();

            return true;
        }

        const now = new Date();

        try {
            this.manifest = await loadManifest();
        } catch {
            this.manifest = null;
        }

        if (this.manifest === null) {
            this.result = {
                status: 'warning',
                reasonCode: 'no_manifest',
                title: 'Vérification impossible',
                reason: 'Liste du jour non téléchargée : connectez-vous au réseau une fois avant le départ',
                holder: null,
                details: [],
            };
        } else {
            const check = checkOffline(this.manifest, await allQueued(), code, now);
            await queueScan(code, check, now);
            await this.countPending();
            this.result = check;
            navigator.vibrate?.(check.status === 'refused' ? [80, 60, 80] : 80);
        }

        // After x-show has displayed the verdict.
        this.$nextTick(() => requestAnimationFrame(() => this.$refs.next?.focus()));

        return false;
    },

    async submitReceipt(event) {
        event?.preventDefault();

        const code = this.$refs.receiptInput.value.trim();
        if (this.busy || this.result !== null || code === '') {
            return;
        }

        this.busy = true;
        let sent = false;
        try {
            sent = await this.verify(code, () => {
                // Reloaded if the page comes back from the back/forward cache (forms.js).
                this.$refs.receipt.dataset.submitting = '1';
                this.$refs.receipt.submit();
            });
        } finally {
            // Sent online: "Vérification…" stays until the verdict page opens.
            this.busy = sent;
        }
    },

    // "Scanner le suivant" of the offline verdict: back to the camera.
    next() {
        this.result = null;
        this.$refs.receiptInput.value = '';

        if (this.state === 'sending' && this.scanner) {
            try {
                this.scanner.resume();
            } catch {
                // Camera stopped meanwhile.
            }
            this.state = 'scanning';
        } else if (this.state === 'sending') {
            this.state = 'idle';
        }
    },
});
