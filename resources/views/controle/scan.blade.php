<x-layouts.controle title="Scanner">
    {{--
        Online: the code is sent to the server (verdict page).
        Offline: checked against the list of the day kept on the phone
        (resources/js/controle/offline.js), verdict shown below, scan queued.
    --}}
    <div
        x-data="scanner"
        data-user-id="{{ auth()->id() }}"
        data-manifest-url="{{ route('api.controle.manifest') }}"
        data-sync-url="{{ route('api.controle.scans.sync') }}"
        data-ping-url="{{ route('api.me') }}"
        class="flex flex-1 flex-col gap-6"
    >
        <h1 class="text-2xl font-bold">Scanner un titre</h1>

        {{-- Offline list of the day and scans waiting to be sent. --}}
        <div x-cloak class="flex flex-col gap-1.5 rounded-2xl bg-white/5 px-4 py-3 text-base ring-1 ring-white/10" role="status" aria-live="polite" data-offline-status>
            <p class="flex items-center gap-2">
                <x-lucide-list-checks class="size-5 shrink-0 text-success-400" aria-hidden="true" x-show="manifestReady" />
                <x-lucide-list-x class="size-5 shrink-0 text-warning-400" aria-hidden="true" x-show="manifestMissing" />
                <span x-text="manifestLabel" data-manifest-label></span>
            </p>
            <p class="flex items-center gap-2 font-semibold text-warning-300" x-show="hasPending">
                <x-lucide-cloud-upload class="size-5 shrink-0" aria-hidden="true" />
                <span x-text="pendingLabel" data-pending-label></span>
            </p>
            <p class="flex items-center gap-2 font-semibold text-danger-300" x-show="hasDropped" data-dropped-label>
                <x-lucide-trash-2 class="size-5 shrink-0" aria-hidden="true" />
                <span x-text="droppedLabel"></span>
            </p>
        </div>

        {{-- Camera: needs JavaScript (hidden without it, the receipt number below always works). --}}
        <section x-cloak class="flex flex-col gap-4" aria-label="Caméra">
            <div class="relative overflow-hidden rounded-3xl bg-black ring-1 ring-white/10" x-show="cameraVisible">
                <div id="scanner-camera" x-ref="camera" class="w-full [&_video]:w-full! [&_video]:h-auto!"></div>
            </div>

            <p class="flex min-h-12 items-center justify-center gap-3 rounded-2xl bg-white/5 px-4 py-3 text-center text-lg font-semibold ring-1 ring-white/10" role="status" aria-live="polite" x-show="starting">
                <x-lucide-camera class="size-6 shrink-0 text-slate-300" aria-hidden="true" />
                Autorisez la caméra si le téléphone le demande…
            </p>

            <p class="flex min-h-12 items-center justify-center gap-3 rounded-2xl bg-white/5 px-4 py-3 text-center text-lg font-semibold ring-1 ring-white/10" role="status" aria-live="polite" x-show="scanning">
                <x-lucide-scan-line class="size-6 shrink-0 text-primary-300" aria-hidden="true" />
                Visez le QR code du reçu ou du téléphone
            </p>

            <p class="flex min-h-12 items-center justify-center gap-3 rounded-2xl bg-primary-600 px-4 py-3 text-center text-lg font-semibold" role="status" aria-live="assertive" x-show="sending">
                <x-lucide-loader-circle class="size-6 shrink-0 animate-spin" aria-hidden="true" />
                Vérification…
            </p>

            <div class="flex flex-col items-center gap-4 rounded-3xl bg-white/5 px-6 py-8 text-center ring-1 ring-white/10" role="alert" x-show="failed">
                <x-lucide-camera-off class="size-14 text-warning-400" aria-hidden="true" />
                <p class="text-lg font-semibold" x-text="message"></p>
                <button type="button" x-on:click="retry" class="inline-flex min-h-14 w-full items-center justify-center gap-2.5 rounded-2xl bg-white px-6 text-lg font-semibold text-slate-900 active:bg-slate-200">
                    <x-lucide-rotate-ccw class="size-5" aria-hidden="true" />
                    Réessayer
                </button>
                <p class="text-base text-slate-300">Ou saisissez le numéro du reçu ci-dessous.</p>
            </div>

            <form method="POST" action="{{ route('controle.scan.store') }}" x-ref="form" class="hidden">
                @csrf
                <input type="hidden" name="code" x-ref="code">
            </form>
        </section>

        {{-- Subscription receipt number typed by hand: works without camera and without JavaScript (tickets: QR code only, VerifyPass). --}}
        <form method="POST" action="{{ route('controle.scan.store') }}" x-ref="receipt" x-on:submit="submitReceipt" data-no-loading class="flex flex-col gap-3 rounded-3xl bg-white/5 p-5 ring-1 ring-white/10">
            @csrf
            <label for="receipt-code" class="text-lg font-semibold">N° de reçu</label>
            <input
                id="receipt-code"
                name="code"
                type="text"
                x-ref="receiptInput"
                value="{{ old('code') }}"
                required
                maxlength="200"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                enterkeyhint="go"
                placeholder="BA-2026-0017"
                aria-describedby="receipt-code-hint{{ $errors->has('code') ? ' receipt-code-error' : '' }}"
                @error('code') aria-invalid="true" @enderror
                @class([
                    'min-h-14 w-full rounded-2xl border-0 bg-white/10 px-4 text-xl font-semibold tracking-wide text-white uppercase placeholder:font-normal placeholder:text-slate-500 focus:ring-2 focus:ring-primary-400',
                    'ring-2 ring-danger-500' => $errors->has('code'),
                    'ring-1 ring-white/20' => ! $errors->has('code'),
                ])
            >
            <p id="receipt-code-hint" class="text-base text-slate-300">
                Abonnements seulement (<span class="whitespace-nowrap">BA-2026-0017</span>) : comparez ensuite le nom et la photo.
                Ticket du jour : scannez son QR code, le numéro n’est pas accepté.
            </p>
            @error('code')
                <p id="receipt-code-error" class="flex items-center gap-2 text-base font-semibold text-danger-300" role="alert">
                    <x-lucide-circle-alert class="size-5 shrink-0" aria-hidden="true" />
                    {{ $message }}
                </p>
            @enderror
            <x-button type="submit" size="lg" block icon="search-check" loading-text="Vérification…">Vérifier</x-button>
        </form>

        <a href="{{ route('controle.scans.index') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl px-4 text-base font-semibold text-slate-300 hover:bg-white/10">
            <x-lucide-history class="size-5" aria-hidden="true" />
            Mes scans du jour
        </a>

        {{-- Offline verdict: same look as <x-scan-result fullscreen>, texts set with x-text (textContent). --}}
        <div
            x-cloak
            x-show="showingResult"
            x-bind:class="resultClass"
            x-bind:data-scan-status="resultStatus"
            role="status"
            aria-live="assertive"
            data-offline-result
            class="@container fixed inset-0 z-50 flex min-w-0 flex-col items-center justify-center-safe gap-6 overflow-y-auto px-6 py-10 text-center"
        >
            <x-lucide-circle-check class="size-24 animate-pop sm:size-32" stroke-width="2.5" aria-hidden="true" x-show="isValid" />
            <x-lucide-circle-x class="size-24 animate-pop sm:size-32" stroke-width="2.5" aria-hidden="true" x-show="isRefused" />
            <x-lucide-triangle-alert class="size-24 animate-pop sm:size-32" stroke-width="2.5" aria-hidden="true" x-show="isWarning" />

            <p class="text-[clamp(2.25rem,14cqw,5rem)] leading-none font-black tracking-tight" x-text="resultLabel" data-result-label></p>

            <p class="inline-flex items-center gap-2 rounded-full bg-black/20 px-4 py-1.5 text-base font-bold">
                <x-lucide-wifi-off class="size-5 shrink-0" aria-hidden="true" />
                <span x-text="resultBadge">Hors ligne</span>
            </p>

            <p class="max-w-md text-xl font-semibold sm:text-2xl" x-text="resultReason" data-result-reason></p>

            <p class="text-xl font-bold" x-show="hasHolder" x-text="resultHolder" data-result-holder></p>

            <div class="flex w-full max-w-sm flex-col gap-3 text-lg">
                <p class="text-xl font-bold" x-text="resultTitle" data-result-title></p>

                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl bg-black/15 px-4 py-3 text-left text-base" x-show="hasRows" data-result-details>
                    <template x-for="row in resultRows" x-bind:key="row.label">
                        <div class="contents">
                            <dt class="opacity-80" x-text="row.label"></dt>
                            <dd class="font-semibold" x-text="row.value"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            <div class="w-full max-w-sm pt-2">
                <button type="button" x-ref="next" x-on:click="next" class="inline-flex min-h-14 w-full items-center justify-center gap-2.5 rounded-full bg-white px-7 text-lg font-medium text-slate-900 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 active:bg-slate-100">
                    <x-lucide-scan-line class="size-5 shrink-0" aria-hidden="true" />
                    Scanner le suivant
                </button>
            </div>
        </div>
    </div>
</x-layouts.controle>
