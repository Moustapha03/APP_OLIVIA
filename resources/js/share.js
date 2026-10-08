/**
 * "Partager le PDF" ([data-share-file]): sends the receipt file itself to
 * WhatsApp (or any app) through the phone's share sheet (Web Share API).
 *
 * The button stays hidden where files cannot be shared (most computers):
 * "Télécharger" and "Envoyer par WhatsApp" remain. The PDF is fetched in
 * advance, because Safari only allows share() right after the tap.
 */
const supportsFileSharing = () => {
    try {
        return typeof navigator.canShare === 'function'
            && navigator.canShare({ files: [new File([''], 'test.pdf', { type: 'application/pdf' })] });
    } catch {
        return false;
    }
};

window.addEventListener('load', () => {
    const buttons = document.querySelectorAll('[data-share-file]');

    if (buttons.length === 0 || !supportsFileSharing()) {
        return;
    }

    buttons.forEach((button) => {
        const file = fetch(button.dataset.shareFile, { credentials: 'same-origin' })
            .then((response) => (response.ok ? response.blob() : Promise.reject(new Error(String(response.status)))))
            .then((blob) => new File([blob], button.dataset.shareName || 'recu.pdf', { type: 'application/pdf' }))
            .catch(() => null);

        file.then((ready) => {
            if (ready) {
                button.hidden = false;
            }
        });

        button.addEventListener('click', async () => {
            const ready = await file;

            if (!ready) {
                return;
            }

            try {
                await navigator.share({ files: [ready], title: button.dataset.shareText, text: button.dataset.shareText });
            } catch {
                // Cancelled by the user: nothing to do.
            }
        });
    });
});
