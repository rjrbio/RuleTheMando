/**
 * app-ui.js — helpers de UI compartidos por toda la app.
 *
 * Cargado automaticamente desde includes/footer.php tras bootstrap.bundle,
 * asi que los listeners se enlazan despues de que Bootstrap este disponible.
 *
 * Caracteristicas:
 *
 *   1. Loading state automatico en cualquier <form>:
 *      al hacer submit, deshabilita el boton y muestra un spinner.
 *      Se restaura tras 30s por seguridad (por si el navegador se queda
 *      cargando indefinidamente). Para opt-out: data-loading="off" en el form.
 *
 *   2. Confirm dialog en lugar de window.confirm() del navegador:
 *      cualquier <form data-confirm="texto"> o boton/link con
 *      data-confirm pide confirmacion via modal Bootstrap antes de
 *      ejecutar la accion. Mas estetico y responsive que confirm().
 *
 *      <form method="post" data-confirm="¿Borrar este juego?">
 *      <a href="..." data-confirm="¿Estas seguro?">
 *
 *      Variante con clase de boton: data-confirm-class="btn-warning"
 *      para acciones no destructivas.
 */

(function () {
    'use strict';

    // ----- Loading state en submits -----
    // OJO: si el submit lo dispara un boton con name (e.g. <button name="login">),
    // ese par name/value SOLO viaja en el POST si el boton NO esta disabled cuando
    // el navegador construye el FormData. Si lo deshabilitamos sincrono dentro del
    // submit handler, el name se pierde y los handlers PHP que detectan la accion
    // con isset($_POST['login']) / ['register'] / ['add_game'] / ['user_action']
    // no se ejecutan -> la pagina parece "solo recargarse". Por eso diferimos el
    // disabled a setTimeout(0): el FormData ya esta construido para entonces.
    function applyLoadingState(form, submitter) {
        var btn = submitter || form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        var original = btn.innerHTML;
        var loadingText = btn.dataset.loadingText || 'Procesando…';
        btn.dataset.originalHtml = original;
        btn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            loadingText;
        setTimeout(function () { btn.disabled = true; }, 0);
        // Restaurar tras 30s por si hay error de red sin recarga
        setTimeout(function () {
            if (btn.dataset.originalHtml) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml;
                delete btn.dataset.originalHtml;
            }
        }, 30000);
    }

    // ----- Confirm dialog reutilizable -----
    function ensureConfirmModal() {
        var modal = document.getElementById('appConfirmModal');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'appConfirmModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
            '  <div class="modal-content">' +
            '    <div class="modal-header">' +
            '      <h5 class="modal-title" data-title>Confirmar</h5>' +
            '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
            '    </div>' +
            '    <div class="modal-body" data-body></div>' +
            '    <div class="modal-footer">' +
            '      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-cancel>Cancelar</button>' +
            '      <button type="button" class="btn btn-danger" data-confirm>Confirmar</button>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(modal);
        return modal;
    }

    function confirmAction(message, opts, onConfirm) {
        opts = opts || {};
        var modal = ensureConfirmModal();
        modal.querySelector('[data-title]').textContent = opts.title || 'Confirmar';
        modal.querySelector('[data-body]').textContent = message;
        var confirmBtn = modal.querySelector('[data-confirm]');
        confirmBtn.textContent = opts.confirmText || 'Confirmar';
        confirmBtn.className = 'btn ' + (opts.confirmClass || 'btn-danger');
        var cancelBtn = modal.querySelector('[data-cancel]');
        cancelBtn.textContent = opts.cancelText || 'Cancelar';

        // Reemplazar el boton para limpiar listeners previos
        var newBtn = confirmBtn.cloneNode(true);
        confirmBtn.replaceWith(newBtn);
        newBtn.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(modal).hide();
            onConfirm();
        });

        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    // Exponer al global para que otros scripts puedan llamarla
    window.appUi = { confirmAction: confirmAction };

    // Listener global para forms con data-confirm + loading state.
    // Soporta data-confirm en el form (todos los submits piden confirm) y en
    // el boton submitter concreto (solo ese boton pide confirm). Util cuando
    // un mismo form tiene varios botones submit con acciones distintas.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        var btn = e.submitter || null;
        var source = (btn && btn.dataset.confirm) ? btn : (form.dataset.confirm ? form : null);

        if (source && !form.dataset.confirmed) {
            e.preventDefault();
            confirmAction(
                source.dataset.confirm,
                {
                    confirmText: source.dataset.confirmText,
                    confirmClass: source.dataset.confirmClass,
                    title: source.dataset.confirmTitle,
                },
                function () {
                    form.dataset.confirmed = 'true';
                    // Si la confirmacion vino de un boton concreto, hay que
                    // disparar el submit con ese boton para que su name/value
                    // viajen en el POST.
                    if (btn && typeof btn.click === 'function' && btn.dataset.confirm) {
                        btn.click();
                    } else if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            );
            return;
        }

        // Loading state (salvo opt-out)
        if (form.dataset.loading !== 'off') {
            applyLoadingState(form, btn);
        }
    });

    // Confirm para enlaces con data-confirm (e.g. <a href="?delete=...">)
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-confirm]');
        if (!link) return;
        if (link.dataset.confirmed === 'true') return;
        e.preventDefault();
        confirmAction(
            link.dataset.confirm,
            {
                confirmText: link.dataset.confirmText,
                confirmClass: link.dataset.confirmClass,
                title: link.dataset.confirmTitle,
            },
            function () {
                link.dataset.confirmed = 'true';
                window.location.href = link.href;
            }
        );
    });
})();
