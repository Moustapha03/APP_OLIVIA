@php
    $toc = [
        'principes' => 'Principes',
        'couleurs' => 'Couleurs',
        'typographie' => 'Typographie',
        'boutons' => 'Boutons',
        'champs' => 'Champs de formulaire',
        'choix' => 'Choix en cartes',
        'badges' => 'Badges',
        'alertes' => 'Alertes',
        'cartes' => 'Cartes et chiffres clés',
        'titres' => 'Titres de transport',
        'etapes' => 'Étapes',
        'tableaux' => 'Tableaux',
        'modales' => 'Modales et confirmations',
        'vides' => 'États vides',
        'scan' => 'Résultat de scan',
        'layouts' => 'Espaces (layouts)',
        'icones' => 'Icônes',
        'redaction' => 'Rédaction',
    ];

    $stopOptions = collect($stops)->mapWithKeys(fn ($s) => [$s['id'] => $s['name']])->all();

    $statusBadges = [
        'paid' => ['success', 'Payé', 'circle-check'],
        'pending' => ['warning', 'En attente', 'clock'],
        'cancelled' => ['danger', 'Annulé', 'circle-x'],
    ];
@endphp

<x-layouts.admin title="Charte graphique">
    <x-page-header
        title="Charte graphique"
        subtitle="Tous les composants de l’application, en situation. Données fictives. Page réservée au super-admin."
    >
        <x-slot:actions>
            <x-badge variant="primary" icon="palette" size="lg">Version vivante</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="mt-8 grid gap-10 xl:grid-cols-[14rem_minmax(0,1fr)]">
        {{-- Table of contents --}}
        <nav class="hidden xl:block" aria-label="Sommaire de la charte">
            <ul class="sticky top-8 space-y-0.5 text-sm">
                @foreach ($toc as $anchor => $label)
                    <li>
                        <a href="#{{ $anchor }}" class="block rounded-lg px-3 py-1.5 text-slate-600 hover:bg-white hover:text-slate-900">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 space-y-10">

            {{-- ============================== PRINCIPES ============================== --}}
            <x-design.section id="principes" title="Principes" description="Nos utilisateurs ne sont pas à l’aise avec l’informatique. Chaque écran doit se comprendre en 5 secondes, sur un petit téléphone, en plein soleil.">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['timer', 'Compris en 5 secondes', 'Un titre, une phrase, un gros bouton. Le reste est secondaire.'],
                        ['pointer', 'Une action principale', 'Un seul bouton bleu par écran. Les autres actions sont blanches.'],
                        ['message-circle', 'Des mots simples', '« Acheter un ticket », pas « Procéder à l’acquisition ».'],
                        ['traffic-cone', 'La couleur a un sens', 'Vert = valide, rouge = refusé, orange = attention. Toujours avec un mot.'],
                        ['hand', 'Des cibles larges', '48 px de haut minimum : on vise avec le pouce, parfois dans un bus.'],
                        ['zap', 'Toujours une réponse', 'Chargement visible, message clair, confirmation avant l’irréversible.'],
                    ] as [$icon, $title, $text])
                        <x-card>
                            <div class="flex gap-4">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                    <x-dynamic-component :component="'lucide-'.$icon" class="size-6" aria-hidden="true" />
                                </span>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $title }}</p>
                                    <p class="mt-1 text-base text-slate-600">{{ $text }}</p>
                                </div>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </x-design.section>

            {{-- ============================== COULEURS ============================== --}}
            <x-design.section id="couleurs" title="Couleurs" description="Une seule couleur de marque (bleu lagune). Les couleurs de statut sont réservées à leur sens : ne jamais utiliser le vert ou le rouge pour décorer.">
                @php
                    $palettes = [
                        ['Principale · primary', 'Boutons principaux, liens, élément actif', [
                            ['50', 'bg-primary-50'], ['100', 'bg-primary-100'], ['200', 'bg-primary-200'], ['300', 'bg-primary-300'], ['400', 'bg-primary-400'],
                            ['500', 'bg-primary-500'], ['600', 'bg-primary-600'], ['700', 'bg-primary-700'], ['800', 'bg-primary-800'], ['900', 'bg-primary-900'], ['950', 'bg-primary-950'],
                        ]],
                        ['Valide · success', 'Titre valide, paiement reçu, caisse équilibrée', [
                            ['50', 'bg-success-50'], ['100', 'bg-success-100'], ['500', 'bg-success-500'], ['600', 'bg-success-600'], ['700', 'bg-success-700'], ['800', 'bg-success-800'],
                        ]],
                        ['Refusé · danger', 'Titre refusé, erreur, action destructrice', [
                            ['50', 'bg-danger-50'], ['100', 'bg-danger-100'], ['500', 'bg-danger-500'], ['600', 'bg-danger-600'], ['700', 'bg-danger-700'], ['800', 'bg-danger-800'],
                        ]],
                        ['Attention · warning', 'Expire bientôt, hors ligne, écart de caisse', [
                            ['50', 'bg-warning-50'], ['100', 'bg-warning-100'], ['400', 'bg-warning-400'], ['500', 'bg-warning-500'], ['600', 'bg-warning-600'], ['700', 'bg-warning-700'], ['800', 'bg-warning-800'],
                        ]],
                        ['Neutres · slate', 'Textes, bordures, fonds', [
                            ['50', 'bg-slate-50'], ['100', 'bg-slate-100'], ['200', 'bg-slate-200'], ['300', 'bg-slate-300'], ['400', 'bg-slate-400'],
                            ['500', 'bg-slate-500'], ['600', 'bg-slate-600'], ['700', 'bg-slate-700'], ['800', 'bg-slate-800'], ['900', 'bg-slate-900'], ['950', 'bg-slate-950'],
                        ]],
                    ];
                @endphp

                <div class="space-y-6">
                    @foreach ($palettes as [$name, $usage, $shades])
                        <div>
                            <p class="font-semibold text-slate-900">{{ $name }}</p>
                            <p class="text-sm text-slate-600">{{ $usage }}</p>
                            <div class="mt-3 grid grid-cols-6 gap-2 sm:grid-cols-11">
                                @foreach ($shades as [$shade, $class])
                                    <div>
                                        <div class="h-12 rounded-xl ring-1 ring-black/5 {{ $class }}"></div>
                                        <p class="mt-1 text-center text-xs font-medium text-slate-600">{{ $shade }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-alert variant="info" title="Règle de contraste (AA)">
                    Texte coloré sur fond blanc : nuance <strong>700</strong>. Texte blanc sur fond coloré : nuance <strong>600</strong> ou plus foncée.
                    Exception : le fond orange « ATTENTION » (400) porte du texte <strong>noir</strong>.
                </x-alert>
            </x-design.section>

            {{-- ============================== TYPOGRAPHIE ============================== --}}
            <x-design.section id="typographie" title="Typographie" description="Inter, hébergée par l’application (fonctionne hors ligne). Taille de base 16 px, jamais moins de 14 px. Chiffres à largeur fixe pour les montants.">
                <x-card>
                    <div class="space-y-5">
                        <div class="grid gap-1 sm:grid-cols-[10rem_1fr] sm:items-baseline">
                            <p class="text-sm text-slate-500">Titre de page · 30 px</p>
                            <p class="text-3xl font-bold tracking-tight">Acheter un ticket</p>
                        </div>
                        <div class="grid gap-1 sm:grid-cols-[10rem_1fr] sm:items-baseline">
                            <p class="text-sm text-slate-500">Titre de bloc · 20 px</p>
                            <p class="text-xl font-semibold">Votre abonnement</p>
                        </div>
                        <div class="grid gap-1 sm:grid-cols-[10rem_1fr] sm:items-baseline">
                            <p class="text-sm text-slate-500">Texte · 16 px</p>
                            <p class="text-base">Votre ticket est valable aujourd’hui jusqu’à 23 h 59, pour 2 trajets.</p>
                        </div>
                        <div class="grid gap-1 sm:grid-cols-[10rem_1fr] sm:items-baseline">
                            <p class="text-sm text-slate-500">Texte secondaire · 14 px</p>
                            <p class="text-sm text-slate-600">Acheté le 5 octobre à 07 h 12 par Mobile Money.</p>
                        </div>
                        <div class="grid gap-1 sm:grid-cols-[10rem_1fr] sm:items-baseline">
                            <p class="text-sm text-slate-500">Montant</p>
                            <p class="text-4xl font-bold tracking-tight"><x-money :amount="27000" /></p>
                        </div>
                    </div>
                </x-card>
                <x-design.code>@verbatim<x-money :amount="1500" />   →   1 500 FCFA   (espace insécable, jamais coupé)@endverbatim</x-design.code>
            </x-design.section>

            {{-- ============================== BOUTONS ============================== --}}
            <x-design.section id="boutons" title="Boutons" description="48 px de haut par défaut, 56 px pour l’action principale d’un écran mobile. Toujours un verbe. Un seul bouton bleu par écran.">
                <x-card title="Variantes">
                    <div class="flex flex-wrap gap-3">
                        <x-button icon="ticket">Principal</x-button>
                        <x-button variant="secondary">Secondaire</x-button>
                        <x-button variant="success" icon="check">Valider</x-button>
                        <x-button variant="danger" icon="trash-2">Supprimer</x-button>
                        <x-button variant="ghost">Discret</x-button>
                        <x-button disabled>Désactivé</x-button>
                    </div>
                </x-card>

                <x-card title="Tailles et largeur">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-button size="sm">Petit · 40 px (tableaux)</x-button>
                            <x-button>Normal · 48 px</x-button>
                            <x-button size="lg" icon-right="arrow-right">Grand · 56 px</x-button>
                        </div>
                        <div class="max-w-sm">
                            <x-button size="lg" block icon="ticket-plus">Acheter un ticket</x-button>
                        </div>
                    </div>
                </x-card>

                <x-card title="Chargement" subtitle="Automatique sur tout bouton de type « submit » : le formulaire ne peut pas partir deux fois.">
                    <form method="POST" action="{{ route('design.demo') }}" class="flex flex-wrap gap-3">
                        @csrf
                        <input type="hidden" name="type" value="confirm">
                        <x-button type="submit">Essayer (spinner)</x-button>
                        <x-button type="submit" variant="secondary" loading-text="Paiement en cours…" icon="smartphone">Essayer (avec texte)</x-button>
                    </form>
                </x-card>

                <x-design.code>@verbatim<x-button type="submit" size="lg" block loading-text="Paiement en cours…">Payer 1 500 FCFA</x-button>
<x-button :href="route('espace.passes.index')" variant="secondary" icon="qr-code">Mes titres</x-button>@endverbatim</x-design.code>
            </x-design.section>

            {{-- ============================== CHAMPS ============================== --}}
            <x-design.section id="champs" title="Champs de formulaire" description="Label toujours visible au-dessus du champ (jamais seulement un texte grisé dedans). Les champs non obligatoires sont marqués « facultatif ». Erreurs vérifiées en quittant le champ, en français simple.">
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-card title="Essayez" subtitle="Laissez vide ou tapez un mauvais numéro, puis validez.">
                        <form method="POST" action="{{ route('design.demo') }}" class="space-y-5">
                            @csrf
                            <input type="hidden" name="type" value="form">
                            <x-input
                                name="phone"
                                label="Numéro de téléphone"
                                type="tel"
                                prefix="+225"
                                inputmode="numeric"
                                autocomplete="tel-national"
                                placeholder="07 00 00 00 00"
                                pattern="(01|05|07|25|27)[0-9]{8}"
                                data-pattern-message="Le numéro doit avoir 10 chiffres et commencer par 01, 05, 07, 25 ou 27."
                                hint="10 chiffres, sans espaces."
                                required
                            />
                            <x-select name="stop_id" label="Votre arrêt" :options="$stopOptions" placeholder="Choisissez un arrêt" required />
                            <x-button type="submit" block>Vérifier</x-button>
                        </form>
                    </x-card>

                    <x-card title="Autres champs">
                        <div class="space-y-5">
                            <x-input name="demo_name" label="Nom complet" autocomplete="name" />
                            <x-input name="demo_password" label="Mot de passe" type="password" autocomplete="new-password" minlength="12" hint="12 caractères minimum." required />
                            <x-input name="demo_search" label="Rechercher un client" icon="search" placeholder="Nom ou téléphone" />
                            <x-textarea name="demo_reason" label="Motif" placeholder="Expliquez en une phrase" />

                            <x-field id="demo-error" label="Exemple d’erreur serveur" error="Ce numéro est déjà utilisé par un autre client." required>
                                <div class="flex min-h-12 items-stretch overflow-hidden rounded-2xl bg-white ring-2 ring-inset ring-danger-600">
                                    <input id="demo-error" type="tel" value="0700000000" aria-invalid="true" aria-describedby="demo-error-error" class="block w-full border-0 bg-transparent px-3.5 py-3 text-base focus:ring-0">
                                </div>
                            </x-field>
                        </div>
                    </x-card>
                </div>
                <x-design.code>@verbatim<x-input name="phone" label="Numéro de téléphone" type="tel" prefix="+225"
         inputmode="numeric" autocomplete="tel-national" required />
<x-select name="stop_id" label="Votre arrêt" :options="$stops" placeholder="Choisissez un arrêt" required />@endverbatim</x-design.code>
            </x-design.section>

            {{-- ============================== CHOIX ============================== --}}
            <x-design.section id="choix" title="Choix en cartes" description="Pour choisir une formule ou un moyen de paiement : toute la carte est cliquable, le prix est visible tout de suite.">
                <div class="max-w-lg">
                    <fieldset class="space-y-3">
                        <legend class="mb-3 text-base font-medium text-slate-800">Quelle formule ?</legend>
                        <x-choice name="demo_formula" value="daily" title="Ticket du jour" description="2 trajets, valable aujourd’hui" :amount="1500" icon="ticket" checked />
                        <x-choice name="demo_formula" value="monthly" title="Abonnement 30 jours" description="Trajets illimités, avec votre photo" :amount="27000" icon="id-card" />
                    </fieldset>
                </div>
            </x-design.section>

            {{-- ============================== BADGES ============================== --}}
            <x-design.section id="badges" title="Badges" description="Un statut en un coup d’œil. Toujours un mot avec la couleur (daltonisme, écran au soleil).">
                <x-card>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-badge variant="success" icon="circle-check">Valide</x-badge>
                        <x-badge variant="danger" icon="circle-x">Refusé</x-badge>
                        <x-badge variant="warning" icon="clock">Expire dans 3 jours</x-badge>
                        <x-badge variant="primary" icon="smartphone">Mobile Money</x-badge>
                        <x-badge icon="banknote">Espèces</x-badge>
                        <x-badge variant="success" icon="lock-open" size="lg">Caisse ouverte</x-badge>
                    </div>
                </x-card>
            </x-design.section>

            {{-- ============================== ALERTES ============================== --}}
            <x-design.section id="alertes" title="Alertes et messages" description="Les messages après une action (« flash ») utilisent ces alertes. Dire ce qui s’est passé, puis quoi faire.">
                <div class="grid gap-3 lg:grid-cols-2">
                    <x-alert variant="success" title="Paiement reçu">Votre ticket est prêt. Montrez le QR code au contrôleur.</x-alert>
                    <x-alert variant="danger" title="Paiement refusé">Votre solde est insuffisant. Rechargez votre compte ou payez au guichet.</x-alert>
                    <x-alert variant="warning" title="Votre abonnement expire dans 3 jours">Renouvelez-le pour continuer à voyager sans interruption.</x-alert>
                    <x-alert variant="info" dismissible>Vous êtes hors ligne. Les scans sont enregistrés et seront envoyés automatiquement.</x-alert>
                </div>
                <x-design.code>@verbatim// Dans un contrôleur :
return back()->with('success', 'Ticket vendu.');   // success | error | warning | info@endverbatim</x-design.code>
            </x-design.section>

            {{-- ============================== CARTES ============================== --}}
            <x-design.section id="cartes" title="Cartes et chiffres clés">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-stat label="Recettes du jour" icon="banknote"><x-money :amount="185500" /></x-stat>
                    <x-stat label="Titres vendus" value="142" hint="dont 18 abonnements" icon="ticket" />
                    <x-stat label="Abonnés actifs" value="214" hint="+12 cette semaine" icon="users" variant="success" />
                    <x-stat label="Expirent sous 7 jours" value="23" hint="SMS de rappel envoyé" icon="clock" variant="warning" />
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <x-card title="Ma caisse" subtitle="Ouverte à 06 h 30 avec un fond de 20 000 FCFA">
                        <x-slot:actions><x-badge variant="success" icon="lock-open">Ouverte</x-badge></x-slot:actions>
                        <dl class="grid grid-cols-2 gap-4">
                            <div><dt class="text-sm text-slate-600">Ventes</dt><dd class="text-xl font-bold">36</dd></div>
                            <div><dt class="text-sm text-slate-600">Encaissé</dt><dd class="text-xl font-bold"><x-money :amount="54000" /></dd></div>
                        </dl>
                        <x-slot:footer><x-button variant="secondary" block icon="lock">Clôturer la caisse</x-button></x-slot:footer>
                    </x-card>

                    <x-card title="Tarifs en vigueur" :padding="false">
                        <ul class="divide-y divide-slate-100">
                            @foreach ($stops as $stop)
                                <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                                    <span class="flex items-center gap-3 font-medium"><x-lucide-map-pin class="size-5 text-primary-600" aria-hidden="true" />{{ $stop['name'] }}</span>
                                    <span class="text-right text-sm text-slate-600">
                                        <x-money :amount="$stop['daily']" class="block text-base font-semibold text-slate-900" />
                                        <x-money :amount="$stop['monthly']" /> / mois
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                </div>
            </x-design.section>

            {{-- ============================== TITRES ============================== --}}
            <x-design.section id="titres" title="Titres de transport" description="L’élément signature de l’application : chaque titre a la forme d’un vrai ticket, avec encoches et pointillés. Le QR code ne contient aucune donnée personnelle.">
                <div class="grid max-w-3xl gap-8 md:grid-cols-2">
                    <x-ticket type="daily" title="Grand-Bassam" subtitle="Aujourd’hui, jusqu’à 23 h 59" status="success" status-label="Valide">
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-600">Trajets</dt><dd class="text-lg font-bold">1 sur 2 restant</dd></div>
                            <div><dt class="text-slate-600">Prix payé</dt><dd class="text-lg font-bold"><x-money :amount="1500" /></dd></div>
                        </dl>
                        <x-slot:stub>
                            <div class="flex flex-col items-center gap-3">
                                <x-qr payload="DEMO-DAILY-0000-NOT-VALID" label="QR code du ticket (démonstration)" />
                                <p class="text-sm text-slate-600">Montrez ce code au contrôleur</p>
                            </div>
                        </x-slot:stub>
                    </x-ticket>

                    <x-ticket type="monthly" title="Bonoua" subtitle="Du 5 octobre au 3 novembre" status="warning" status-label="Expire dans 3 jours">
                        <div class="flex items-center gap-3">
                            <span class="flex size-14 items-center justify-center rounded-2xl bg-white/15 text-xl font-bold" aria-hidden="true">AK</span>
                            <div>
                                <p class="text-lg font-semibold">Awa Koné</p>
                                <p class="text-sm text-primary-100">Valable jusqu’à Bonoua et les arrêts avant</p>
                            </div>
                        </div>
                        <x-slot:stub>
                            <div class="flex flex-col items-center gap-3">
                                <x-qr payload="DEMO-MONTHLY-0000-NOT-VALID" label="QR code de l’abonnement (démonstration)" />
                                <x-button variant="secondary" block icon="refresh-cw">Renouveler</x-button>
                            </div>
                        </x-slot:stub>
                    </x-ticket>
                </div>
            </x-design.section>

            {{-- ============================== ÉTAPES ============================== --}}
            <x-design.section id="etapes" title="Étapes" description="Parcours d’achat en 3 étapes maximum : Arrêt → Formule → Paiement.">
                <div class="grid gap-4 lg:grid-cols-3">
                    @foreach ([1, 2, 3] as $step)
                        <x-card>
                            <x-stepper :steps="['Arrêt', 'Formule', 'Paiement']" :current="$step" />
                        </x-card>
                    @endforeach
                </div>
            </x-design.section>

            {{-- ============================== TABLEAUX ============================== --}}
            <x-design.section id="tableaux" title="Tableaux" description="Réservés au guichet et à l’administration. Défilent horizontalement sur téléphone. Montants alignés à droite.">
                <x-table caption="Ventes du jour (démonstration)">
                    <x-slot:head>
                        <th>Heure</th><th>Client</th><th>Arrêt</th><th>Formule</th><th>Paiement</th><th class="text-right">Montant</th><th>Statut</th>
                    </x-slot:head>
                    @foreach ($sales as $sale)
                        @php [$variant, $label, $icon] = $statusBadges[$sale['status']]; @endphp
                        <tr>
                            <td class="tabular-nums">{{ $sale['time'] }}</td>
                            <td class="font-medium">{{ $sale['customer'] }}</td>
                            <td>{{ $sale['stop'] }}</td>
                            <td>{{ $sale['type'] }}</td>
                            <td>{{ $sale['channel'] }}</td>
                            <td class="text-right font-semibold"><x-money :amount="$sale['amount']" /></td>
                            <td><x-badge :variant="$variant" :icon="$icon">{{ $label }}</x-badge></td>
                        </tr>
                    @endforeach
                </x-table>

                <x-table caption="Tableau vide" empty="Aucune vente pour le moment. Les ventes de la journée apparaîtront ici.">
                    <x-slot:head><th>Heure</th><th>Client</th><th class="text-right">Montant</th></x-slot:head>
                </x-table>
            </x-design.section>

            {{-- ============================== MODALES ============================== --}}
            <x-design.section id="modales" title="Modales et confirmations" description="Sur téléphone, la modale monte depuis le bas (plus facile à atteindre avec le pouce). Toute action irréversible passe par une confirmation qui dit clairement la conséquence.">
                <x-card>
                    <div class="flex flex-wrap gap-3">
                        <x-button variant="secondary" icon="circle-help" data-modal-open="demo-help">Ouvrir une modale</x-button>
                        <x-button variant="danger" icon="ban" data-modal-open="demo-confirm">Annuler une vente</x-button>
                    </div>
                </x-card>

                <x-modal name="demo-help" title="Comment acheter un ticket ?">
                    <ol class="list-decimal space-y-2 pl-5 text-base text-slate-700">
                        <li>Choisissez votre arrêt.</li>
                        <li>Choisissez ticket du jour ou abonnement.</li>
                        <li>Payez avec Mobile Money. Votre QR code arrive tout de suite.</li>
                    </ol>
                    <x-slot:footer>
                        <x-button x-on:click="$dispatch('close')">J’ai compris</x-button>
                    </x-slot:footer>
                </x-modal>

                <x-confirm
                    name="demo-confirm"
                    title="Annuler cette vente ?"
                    :action="route('design.demo')"
                    confirm-label="Oui, annuler la vente"
                >
                    Le ticket de <strong>Yao Kouassi</strong> (<x-money :amount="1000" />) ne sera plus valable. Cette action est définitive.
                    <x-slot:fields>
                        <input type="hidden" name="type" value="confirm">
                    </x-slot:fields>
                </x-confirm>

                <x-design.code>@verbatim<x-button variant="danger" data-modal-open="cancel-42">Annuler la vente</x-button>
<x-confirm name="cancel-42" title="Annuler cette vente ?" :action="route('…', $sale)" method="DELETE"
           confirm-label="Oui, annuler la vente">Cette action est définitive.</x-confirm>@endverbatim</x-design.code>
            </x-design.section>

            {{-- ============================== VIDES ============================== --}}
            <x-design.section id="vides" title="États vides" description="Une page vide n’est jamais blanche : elle explique et propose l’action suivante.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-empty-state icon="ticket" title="Vous n’avez pas encore de ticket">
                        Achetez votre premier ticket en moins d’une minute.
                        <x-slot:action><x-button size="lg" block icon="ticket-plus">Acheter un ticket</x-button></x-slot:action>
                    </x-empty-state>
                    <x-empty-state icon="wifi-off" title="Pas de connexion">
                        Vous pouvez continuer à scanner. Les résultats seront envoyés au retour du réseau.
                    </x-empty-state>
                </div>
            </x-design.section>

            {{-- ============================== SCAN ============================== --}}
            <x-design.section id="scan" title="Résultat de scan" description="Lisible à bout de bras dans un bus : tout l’écran change de couleur, un mot géant, la raison, et la photo de l’abonné pour vérifier que c’est bien lui. Son et vibration à l’étape 7.">
                <div class="grid gap-4 md:grid-cols-3">
                    <x-scan-result status="valid" reason="Abonnement · Grand-Bassam" holder="Awa Koné" class="min-h-96" />
                    <x-scan-result status="refused" reason="Ticket déjà scanné il y a 4 minutes" class="min-h-96" />
                    <x-scan-result status="warning" reason="Arrêt plus loin que l’abonnement : complément à payer" class="min-h-96" />
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach (['valid' => 'Valide', 'refused' => 'Refusé', 'warning' => 'Attention'] as $status => $label)
                        <x-button :href="route('design.scan', $status)" variant="secondary" icon="maximize">Plein écran · {{ $label }}</x-button>
                    @endforeach
                </div>
            </x-design.section>

            {{-- ============================== LAYOUTS ============================== --}}
            <x-design.section id="layouts" title="Espaces (layouts)" description="Un layout par espace, chacun adapté à son appareil. Ouvrez-les sur un téléphone ou en 360 px de large.">
                @php
                    $layouts = [
                        'public' => ['globe', 'Public', 'Accueil, arrêts et tarifs, connexion.', 'x-layouts.public'],
                        'espace' => ['smartphone', 'Espace client', 'Téléphone : barre d’onglets en bas.', 'x-layouts.espace'],
                        'guichet' => ['monitor', 'Guichet', 'Tablette / PC : état de la caisse toujours visible.', 'x-layouts.guichet'],
                        'controle' => ['scan-line', 'Contrôle', 'Téléphone, fond sombre, état du réseau.', 'x-layouts.controle'],
                        'admin' => ['layout-dashboard', 'Administration', 'Menu latéral, tiroir sur téléphone.', 'x-layouts.admin'],
                    ];
                @endphp
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($layouts as $space => [$icon, $label, $text, $tag])
                        <a href="{{ route('design.layout', $space) }}" class="group rounded-2xl bg-white p-5 shadow-soft ring-1 ring-slate-200/70 transition hover:-translate-y-0.5 hover:shadow-lifted">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                <x-dynamic-component :component="'lucide-'.$icon" class="size-6" aria-hidden="true" />
                            </span>
                            <p class="mt-3 flex items-center gap-1 text-lg font-semibold text-slate-900">
                                {{ $label }}
                                <x-lucide-arrow-right class="size-5 transition group-hover:translate-x-0.5" aria-hidden="true" />
                            </p>
                            <p class="mt-1 text-base text-slate-600">{{ $text }}</p>
                            <code class="mt-3 inline-block rounded-lg bg-slate-100 px-2 py-1 text-sm text-slate-700">&lt;{{ $tag }}&gt;</code>
                        </a>
                    @endforeach
                </div>
            </x-design.section>

            {{-- ============================== ICÔNES ============================== --}}
            <x-design.section id="icones" title="Icônes" description="Lucide, trait de 2 px, 20 px dans le texte et les boutons. Toujours accompagnées d’un mot.">
                <x-card>
                    <div class="grid grid-cols-3 gap-4 sm:grid-cols-6 lg:grid-cols-8">
                        @foreach (['bus', 'ticket', 'id-card', 'qr-code', 'scan-line', 'map-pin', 'banknote', 'smartphone', 'wallet', 'users', 'user-round', 'clock', 'circle-check', 'circle-x', 'triangle-alert', 'wifi-off', 'printer', 'refresh-cw', 'search', 'lock-open', 'lock', 'tag', 'chart-column', 'settings'] as $icon)
                            <div class="flex flex-col items-center gap-2 rounded-xl p-2 text-center">
                                <x-dynamic-component :component="'lucide-'.$icon" class="size-6 text-slate-700" aria-hidden="true" />
                                <code class="text-xs text-slate-600">{{ $icon }}</code>
                            </div>
                        @endforeach
                    </div>
                </x-card>
                <x-design.code>@verbatim<x-lucide-bus class="size-5" aria-hidden="true" />     ou     <x-button icon="bus">…</x-button>@endverbatim</x-design.code>
            </x-design.section>

            {{-- ============================== RÉDACTION ============================== --}}
            <x-design.section id="redaction" title="Rédaction" description="Phrases courtes, verbes d’action, vouvoiement. On dit ce qui se passe et ce qu’il faut faire.">
                <x-table caption="Exemples de formulations">
                    <x-slot:head><th>À écrire</th><th>À éviter</th></x-slot:head>
                    @foreach ([
                        ['Acheter un ticket', 'Procéder à l’acquisition d’un titre'],
                        ['Paiement refusé. Réessayez ou payez au guichet.', 'Erreur 402 : transaction échouée'],
                        ['Ce ticket a déjà été scanné il y a 4 minutes.', 'Rejeu détecté (anti-replay)'],
                        ['Votre abonnement expire dans 3 jours.', 'Échéance J-3'],
                        ['Oui, annuler la vente', 'OK'],
                        ['Numéro de téléphone', 'MSISDN'],
                    ] as [$do, $dont])
                        <tr>
                            <td><span class="inline-flex items-start gap-2"><x-lucide-check class="mt-0.5 size-5 shrink-0 text-success-600" aria-hidden="true" />{{ $do }}</span></td>
                            <td class="text-slate-600"><span class="inline-flex items-start gap-2"><x-lucide-x class="mt-0.5 size-5 shrink-0 text-danger-600" aria-hidden="true" />{{ $dont }}</span></td>
                        </tr>
                    @endforeach
                </x-table>
            </x-design.section>
        </div>
    </div>
</x-layouts.admin>
