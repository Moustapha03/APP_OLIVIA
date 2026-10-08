/**
 * Thermal receipts (resources/views/components/layouts/print.blade.php):
 * - [data-print] button prints the page;
 * - [data-auto-print] opens the print dialog once the page is shown
 *   ("Encaisser et imprimer").
 */
document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-print]')) {
        window.print();
    }
});

window.addEventListener('load', () => {
    if (document.querySelector('[data-auto-print]')) {
        // Let the QR code paint before the dialog freezes the page.
        setTimeout(() => window.print(), 300);
    }
});
