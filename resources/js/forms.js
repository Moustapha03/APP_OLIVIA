/**
 * Form behaviours shared by every page (plain JS, no Alpine needed).
 *
 * 1. Submit buttons ([data-loading]) show a spinner and the form cannot be
 *    sent twice (no double payment, no double sale).
 * 2. Live validation of [data-validate] fields with plain French messages,
 *    instead of the browser's own bubbles.
 * 3. "Afficher / Masquer" toggle on password fields.
 * 4. [data-modal-open="name"] opens <x-modal name="name">.
 */

const MESSAGES = {
    valueMissing: (el) => (el.type === 'checkbox' ? 'Cochez cette case pour continuer.' : el.tagName === 'SELECT' ? 'Choisissez une option.' : 'Ce champ est obligatoire.'),
    typeMismatch: (el) => (el.type === 'email' ? 'Adresse e-mail invalide. Exemple : nom@exemple.com' : 'Format invalide.'),
    tooShort: (el) => `Au moins ${el.minLength} caractères (vous en avez saisi ${el.value.length}).`,
    tooLong: (el) => `${el.maxLength} caractères maximum.`,
    patternMismatch: (el) => el.dataset.patternMessage || 'Format invalide.',
    rangeUnderflow: (el) => `La valeur minimale est ${el.min}.`,
    rangeOverflow: (el) => `La valeur maximale est ${el.max}.`,
    stepMismatch: () => 'Valeur non valide.',
    badInput: () => 'Saisissez un nombre.',
};

function messageFor(el) {
    for (const [key, build] of Object.entries(MESSAGES)) {
        if (el.validity[key]) {
            return build(el);
        }
    }

    return el.validationMessage;
}

function errorBox(el) {
    return document.querySelector(`[data-error-for="${CSS.escape(el.id)}"]`);
}

function showError(el, message) {
    const box = errorBox(el);
    const shell = el.closest('[data-field-shell]') ?? el;

    el.setAttribute('aria-invalid', 'true');
    shell.classList.remove('ring-slate-300');
    shell.classList.add('ring-danger-600');

    if (box) {
        box.querySelector('[data-error-text]').textContent = message;
        box.classList.remove('hidden');
    }
}

function clearError(el) {
    const box = errorBox(el);
    const shell = el.closest('[data-field-shell]') ?? el;

    el.removeAttribute('aria-invalid');
    shell.classList.remove('ring-danger-600');
    shell.classList.add('ring-slate-300');

    if (box) {
        box.classList.add('hidden');
    }
}

function validate(el) {
    if (el.checkValidity()) {
        clearError(el);
        return true;
    }

    showError(el, messageFor(el));
    return false;
}

// -- 2. Live validation -------------------------------------------------------

// Validate when leaving a field (not while typing: less stress).
document.addEventListener('focusout', (event) => {
    const el = event.target;
    if (el.matches?.('[data-validate]') && el.value !== '') {
        validate(el);
    }
});

// Once a field shows an error, re-check it as the user corrects it.
document.addEventListener('input', (event) => {
    const el = event.target;
    if (el.matches?.('[data-validate][aria-invalid="true"]')) {
        validate(el);
    }
});

// On submit, the browser fires "invalid" on each wrong field: show our
// message instead of the native bubble, and focus the first one.
document.addEventListener(
    'invalid',
    (event) => {
        const el = event.target;
        if (!el.matches?.('[data-validate]')) {
            return;
        }

        event.preventDefault();
        showError(el, messageFor(el));

        const form = el.form;
        if (form && !form.dataset.focusedInvalid) {
            form.dataset.focusedInvalid = '1';
            el.focus();
            requestAnimationFrame(() => delete form.dataset.focusedInvalid);
        }
    },
    true,
);

// -- 1. Loading state + no double submit --------------------------------------

function setLoading(button) {
    const spinner = button.querySelector('[data-spinner]');
    const label = button.querySelector('[data-label]');
    const text = button.querySelector('[data-text]');
    const loadingText = button.dataset.loadingText;

    button.setAttribute('aria-busy', 'true');
    button.disabled = true;

    if (loadingText && text) {
        // Keep a visible label: "Paiement en cours…"
        text.textContent = loadingText;
        spinner?.classList.remove('absolute', 'inset-0');
        label?.prepend(spinner);
        spinner?.removeAttribute('hidden');
    } else {
        label?.classList.add('invisible');
        spinner?.removeAttribute('hidden');
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    // Search / filter forms: no lock needed.
    if (form.hasAttribute('data-no-loading')) {
        return;
    }

    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = '1';

    const button = event.submitter?.matches('[data-loading]') ? event.submitter : form.querySelector('button[data-loading]');

    // Disable on the next tick so the clicked button's name/value is still sent.
    if (button) {
        setTimeout(() => setLoading(button), 0);
    }
});

// Back/forward cache: a page restored with a locked form is reloaded fresh.
window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.querySelector('form[data-submitting]')) {
        window.location.reload();
    }
});

// -- 3. Password visibility ---------------------------------------------------

document.addEventListener('click', (event) => {
    const toggle = event.target.closest?.('[data-password-toggle]');
    if (!toggle) {
        return;
    }

    const input = document.getElementById(toggle.dataset.passwordToggle);
    const visible = input.type === 'password';

    input.type = visible ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
    toggle.querySelector('[data-toggle-label]').textContent = visible ? 'Masquer' : 'Afficher';
    input.focus();
});

// -- 4. Modal triggers --------------------------------------------------------

document.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('[data-modal-open]');
    if (!trigger) {
        return;
    }

    event.preventDefault();
    window.dispatchEvent(new CustomEvent('open-modal', { detail: trigger.dataset.modalOpen }));
});
