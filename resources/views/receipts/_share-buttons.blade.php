{{-- Download / WhatsApp / share-the-file buttons of a paid receipt. $payment, $whatsappTo (?string) --}}
<x-button :href="route('receipts.pdf', $payment)" variant="secondary" :block="$block ?? false" icon="download" data-no-loading>Télécharger le reçu (PDF)</x-button>
<x-button :href="route('receipts.whatsapp', $payment)" variant="secondary" :block="$block ?? false" icon="message-circle" target="_blank" rel="noopener" data-no-loading>
    {{ $whatsappTo ? 'Envoyer par WhatsApp au '.$whatsappTo : 'Envoyer par WhatsApp' }}
</x-button>
{{-- Phones only (Web Share API with files): sends the PDF itself. Shown by resources/js/share.js. --}}
<x-button type="button" variant="secondary" :block="$block ?? false" icon="share-2" hidden
    data-share-file="{{ route('receipts.pdf', ['payment' => $payment, 'affichage' => 1]) }}"
    data-share-name="recu-{{ $payment->receipt_number }}.pdf"
    data-share-text="Reçu N° {{ $payment->receipt_number }} – {{ config('app.name') }}">Partager le PDF (WhatsApp…)</x-button>
