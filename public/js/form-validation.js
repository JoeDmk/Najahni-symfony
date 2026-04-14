/**
 * Najahni – real-time form validation
 * Auto-initialises on every <form novalidate>.
 * Validates on blur (first touch), then on every keystroke, and on submit.
 */
(function () {
    'use strict';

    /* ---------- French messages ---------- */
    var MSG = {
        required:  'Ce champ est obligatoire.',
        select:    'Veuillez faire un choix.',
        email:     'Veuillez entrer un email valide.',
        url:       'Veuillez entrer une URL valide.',
        number:    'Veuillez entrer un nombre valide.',
        minlength: 'Minimum {0} caractères requis.',
        maxlength: 'Maximum {0} caractères autorisés.',
        min:       'La valeur minimale est {0}.',
        max:       'La valeur maximale est {0}.',
        dateFuture:'La date doit être dans le futur.',
        datePast:  'La date doit être dans le passé.',
        match:     'Les mots de passe ne correspondent pas.',
        pattern:   'Format invalide.'
    };

    var SKIP = ['hidden', 'submit', 'button', 'file', 'checkbox', 'radio', 'reset'];

    /* ---------- locate / create error element ---------- */
    function findErr(field) {
        var anchor = field.closest('.input-group') || field;
        var node   = anchor.nextElementSibling;
        while (node) {
            if (node.matches && node.matches('.invalid-feedback, .text-danger.small, .nj-fe'))
                return node;
            if (node.classList && node.classList.contains('d-flex')) {
                var inner = node.querySelector('.invalid-feedback, .text-danger.small');
                if (inner) return inner;
            }
            node = node.nextElementSibling;
        }
        return null;
    }

    function getErr(field) {
        var el = findErr(field);
        if (el) return el;
        el = document.createElement('div');
        el.className = 'text-danger small mt-1 nj-fe';
        el.style.display = 'none';
        var anchor = field.closest('.input-group') || field;
        anchor.parentNode.insertBefore(el, anchor.nextSibling);
        return el;
    }

    /* ---------- validate one field ---------- */
    function check(f) {
        if (SKIP.indexOf(f.type) !== -1 || !f.name) return '';
        var v = f.value, t = v.trim();

        // Required
        if (f.hasAttribute('required') && !t)
            return f.tagName === 'SELECT' ? MSG.select : MSG.required;
        if (!t) return '';

        // Type
        if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t)) return MSG.email;
        if (f.type === 'url'   && !/^https?:\/\/.+/.test(t))              return MSG.url;
        if (f.type === 'number' && isNaN(parseFloat(v)))                   return MSG.number;

        // Length
        var mn = f.getAttribute('minlength');
        if (mn && t.length < +mn) return MSG.minlength.replace('{0}', mn);
        var mx = f.getAttribute('maxlength');
        if (mx && t.length > +mx) return MSG.maxlength.replace('{0}', mx);

        // Numeric range
        var mi = f.getAttribute('min'), ma = f.getAttribute('max');
        if (f.type === 'number') {
            if (mi !== null && parseFloat(v) < parseFloat(mi)) return MSG.min.replace('{0}', mi);
            if (ma !== null && parseFloat(v) > parseFloat(ma)) return MSG.max.replace('{0}', ma);
        }

        // Date range
        if (f.type === 'date') {
            if (mi && v < mi) return MSG.dateFuture;
            if (ma && v > ma) return MSG.datePast;
        }

        // Pattern
        var pat = f.getAttribute('pattern');
        if (pat && !new RegExp(pat).test(v)) return f.getAttribute('title') || MSG.pattern;

        // Confirm-password match
        if (f.name === 'confirm_password') {
            var form = f.closest('form');
            var pw = form.querySelector('[name="password"]') || form.querySelector('[name="new_password"]');
            if (pw && t !== pw.value) return MSG.match;
        }

        return '';
    }

    /* ---------- show / clear ---------- */
    function showErr(f, m) {
        f.classList.add('is-invalid');
        var el = getErr(f);
        el.textContent = m;
        el.style.display = '';
        if (el.classList.contains('invalid-feedback')) el.classList.add('d-block');
    }

    function clearErr(f) {
        f.classList.remove('is-invalid');
        var el = findErr(f);
        if (!el) return;
        el.style.display = 'none';
        if (el.classList.contains('invalid-feedback')) el.classList.remove('d-block');
    }

    /* ---------- init one form ---------- */
    function initForm(form) {
        var all     = form.querySelectorAll('input, select, textarea');
        var touched = {};

        function run(f) {
            var e = check(f);
            if (e) showErr(f, e); else clearErr(f);
            return !e;
        }

        all.forEach(function (f) {
            if (SKIP.indexOf(f.type) !== -1 || !f.name) return;
            // If the server already flagged this field, consider it touched
            if (f.classList.contains('is-invalid')) touched[f.name] = true;

            f.addEventListener('blur',   function () { touched[f.name] = true; run(f); });
            f.addEventListener('input',  function () { if (touched[f.name]) run(f); });
            f.addEventListener('change', function () { touched[f.name] = true; run(f); });
        });

        form.addEventListener('submit', function (ev) {
            var ok = true;
            all.forEach(function (f) {
                if (SKIP.indexOf(f.type) !== -1 || !f.name) return;
                touched[f.name] = true;
                if (!run(f)) ok = false;
            });
            if (!ok) ev.preventDefault();
        });
    }

    /* ---------- bootstrap ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[novalidate]').forEach(initForm);
    });
})();
