import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const brand = {
    confirmButtonColor: '#1e3a6e',
    cancelButtonColor: '#64748b',
};

/** Keep body height stable — Swal's default heightAuto breaks our h-screen + sidebar layout. */
const swalDefaults = {
    heightAuto: false,
    scrollbarPadding: false,
};

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4200,
    timerProgressBar: true,
    // Do NOT set heightAuto: false on toasts — it stretches the popup full viewport
    // and leaves a grey strip (timer bar / background) down the page.
    width: 'auto',
    customClass: {
        popup: 'dugsi-toast',
        container: 'dugsi-toast-container',
    },
});

/**
 * Shared UI for modals, confirms, and notifications (SweetAlert2).
 */
const DugsiUI = {
    toast(message, options = {}) {
        const icon = options.icon ?? 'success';

        return Toast.fire({
            icon,
            title: message,
            ...options,
        });
    },

    success(message, options = {}) {
        return this.toast(message, { icon: 'success', ...options });
    },

    error(message, options = {}) {
        return this.toast(message, { icon: 'error', ...options });
    },

    warning(message, options = {}) {
        return this.toast(message, { icon: 'warning', ...options });
    },

    info(message, options = {}) {
        return this.toast(message, { icon: 'info', ...options });
    },

    /**
     * Styled confirm. Returns true when the user confirms.
     */
    async confirm(options = {}) {
        const {
            title = 'Are you sure?',
            text = '',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            icon = 'warning',
            danger = false,
        } = typeof options === 'string' ? { text: options } : options;

        const result = await Swal.fire({
            ...swalDefaults,
            title,
            text,
            icon,
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            reverseButtons: true,
            confirmButtonColor: danger ? '#dc2626' : brand.confirmButtonColor,
            cancelButtonColor: brand.cancelButtonColor,
            customClass: {
                popup: 'dugsi-swal',
                confirmButton: 'dugsi-swal-confirm',
                cancelButton: 'dugsi-swal-cancel',
            },
        });

        return result.isConfirmed;
    },

    /**
     * Open a form/content panel (hidden template node) inside a SweetAlert modal.
     * Pass a CSS selector or HTMLElement. The node is moved into the popup and
     * returned to its original place when the modal closes.
     */
    openModal(target, options = {}) {
        const el = typeof target === 'string' ? document.querySelector(target) : target;
        if (! el) {
            console.warn('DugsiUI.openModal: element not found', target);

            return Promise.resolve();
        }

        const placeholder = document.createComment('dugsi-modal-placeholder');
        el.parentNode?.insertBefore(placeholder, el);
        el.classList.remove('hidden');

        const width = options.width ?? el.dataset.dugsiWidth ?? '28rem';
        const title = options.title ?? el.dataset.dugsiTitle ?? null;

        return Swal.fire({
            ...swalDefaults,
            ...(title ? { title } : {}),
            html: el,
            width,
            showConfirmButton: false,
            showCloseButton: options.showCloseButton ?? true,
            focusConfirm: false,
            allowOutsideClick: options.allowOutsideClick ?? true,
            customClass: {
                popup: 'dugsi-swal dugsi-swal-form',
                htmlContainer: 'dugsi-swal-html',
                title: 'dugsi-swal-title',
                closeButton: 'dugsi-swal-close',
            },
            didOpen: (popup) => {
                options.onOpen?.(popup, el);
            },
            willClose: () => {
                if (placeholder.parentNode) {
                    placeholder.parentNode.insertBefore(el, placeholder);
                    placeholder.remove();
                }
                el.classList.add('hidden');
                options.onClose?.(el);
            },
        });
    },

    close() {
        Swal.close();
    },

    /**
     * Wire declarative attributes once on DOM ready.
     * - [data-dugsi-open="#selector"] opens a modal
     * - [data-dugsi-close] closes the active modal
     * - form[data-dugsi-confirm] intercepts submit
     */
    bind() {
        document.addEventListener('click', async (event) => {
            const openBtn = event.target.closest('[data-dugsi-open]');
            if (openBtn) {
                event.preventDefault();
                const selector = openBtn.getAttribute('data-dugsi-open');
                const title = openBtn.getAttribute('data-dugsi-title') || undefined;
                const width = openBtn.getAttribute('data-dugsi-width') || undefined;
                this.openModal(selector, { title, width });

                return;
            }

            if (event.target.closest('[data-dugsi-close]')) {
                event.preventDefault();
                this.close();
            }
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-dugsi-confirm]');
            if (! form || form.dataset.dugsiConfirmed === '1') {
                return;
            }

            event.preventDefault();
            const ok = await this.confirm({
                title: form.getAttribute('data-dugsi-confirm-title') || 'Please confirm',
                text: form.getAttribute('data-dugsi-confirm') || 'Are you sure?',
                confirmText: form.getAttribute('data-dugsi-confirm-ok') || 'Confirm',
                cancelText: form.getAttribute('data-dugsi-confirm-cancel') || 'Cancel',
                danger: form.hasAttribute('data-dugsi-danger'),
                icon: form.getAttribute('data-dugsi-confirm-icon') || 'warning',
            });

            if (ok) {
                form.dataset.dugsiConfirmed = '1';
                form.requestSubmit();
            }
        });
    },
};

window.DugsiUI = DugsiUI;
window.Swal = Swal;

DugsiUI.bind();

export default DugsiUI;
