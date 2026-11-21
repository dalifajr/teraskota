/**
 * Teras Kota UI Transitions & Micro-Interactions Engine
 * Provides:
 * 1. Slim glowing top loading progress bar on navigation, form submit, and interactive fetch()
 * 2. Smooth page entry & exit transitions
 * 3. Tactile ripple feedback on buttons, menu links, and POS cards
 * 4. Staggered entrance animations for cards, tables, offcanvas menus, and submenus
 */
(function () {
    'use strict';

    // =========================================================================
    // 1. TOP LOADING PROGRESS BAR (TKProgress)
    // =========================================================================
    const TKProgress = {
        status: null,
        trickleTimer: null,
        barEl: null,
        containerEl: null,

        ensureDOM() {
            if (this.containerEl) return;
            const container = document.createElement('div');
            container.id = 'tk-progress-container';
            container.className = 'tk-progress-container';
            container.setAttribute('aria-hidden', 'true');
            container.innerHTML = '<div class="tk-progress-bar"><div class="tk-progress-peg"></div></div>';
            document.documentElement.appendChild(container);
            this.containerEl = container;
            this.barEl = container.querySelector('.tk-progress-bar');
        },

        start() {
            this.ensureDOM();
            if (this.status === null) {
                this.set(0.08);
            }
            clearInterval(this.trickleTimer);
            this.trickleTimer = setInterval(() => {
                this.inc();
            }, 220);
            return this;
        },

        set(n) {
            this.ensureDOM();
            n = Math.min(Math.max(n, 0.08), 1);
            this.status = (n === 1 ? null : n);

            this.containerEl.classList.add('is-active');
            if (this.barEl) {
                this.barEl.style.transform = `scaleX(${n})`;
            }

            if (n === 1) {
                clearInterval(this.trickleTimer);
                setTimeout(() => {
                    if (this.containerEl && this.status === null) {
                        this.containerEl.classList.remove('is-active');
                        if (this.barEl) {
                            this.barEl.style.transform = 'scaleX(0)';
                        }
                    }
                }, 260);
            }
            return this;
        },

        inc() {
            let n = this.status;
            if (n === null) {
                return this.start();
            }
            if (n >= 0.99) {
                return this;
            }
            let amount = 0;
            if (n < 0.25) amount = 0.12;
            else if (n < 0.5) amount = 0.07;
            else if (n < 0.8) amount = 0.035;
            else if (n < 0.95) amount = 0.01;
            else amount = 0.002;

            return this.set(Math.min(n + amount, 0.985));
        },

        done() {
            if (this.status === null && (!this.containerEl || !this.containerEl.classList.contains('is-active'))) {
                return this;
            }
            return this.set(1);
        }
    };

    window.TKProgress = TKProgress;

    // Start initial page load completion animation
    TKProgress.start();
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => TKProgress.done(), 120);
    });
    window.addEventListener('load', () => {
        TKProgress.done();
    });

    // =========================================================================
    // 2. HOOK INTERACTIVE FETCH CALLS TO TOP PROGRESS BAR
    // =========================================================================
    if (window.fetch) {
        const originalFetch = window.fetch.bind(window);
        let activeInteractiveRequests = 0;

        window.fetch = function (...args) {
            const urlStr = typeof args[0] === 'string'
                ? args[0]
                : (args[0] && args[0].url ? String(args[0].url) : '');

            // Ignore silent background polling endpoints so the bar only reacts to user actions
            const isSilentBackgroundPoll =
                urlStr.includes('/notifications/unread') ||
                urlStr.includes('/pos/qris/check-status/') ||
                urlStr.includes('/pos/bootstrap') ||
                urlStr.includes('/billing/invoice/');

            if (!isSilentBackgroundPoll) {
                activeInteractiveRequests++;
                TKProgress.start();
            }

            return originalFetch(...args).finally(() => {
                if (!isSilentBackgroundPoll) {
                    activeInteractiveRequests = Math.max(0, activeInteractiveRequests - 1);
                    if (activeInteractiveRequests === 0) {
                        TKProgress.done();
                    }
                }
            });
        };
    }

    // =========================================================================
    // 3. RIPPLE & TACTILE MICRO-INTERACTIONS
    // =========================================================================
    const RIPPLE_SELECTOR = [
        '.btn',
        '.sidebar-link',
        '.submenu-link',
        '.mobile-nav-item',
        '.pos-cat-btn',
        '.pos-product-card',
        '.pos-btn-pay',
        '.pos-btn-nav',
        '.pos-floating-cart-btn',
        '.pos-floating-pay-btn',
        '.page-link'
    ].join(', ');

    function createRipple(e) {
        const target = e.target.closest(RIPPLE_SELECTOR);
        if (!target || target.disabled || target.classList.contains('disabled')) return;

        const rect = target.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height) * 1.35;
        const clientX = e.clientX || (rect.left + rect.width / 2);
        const clientY = e.clientY || (rect.top + rect.height / 2);

        const x = clientX - rect.left - size / 2;
        const y = clientY - rect.top - size / 2;

        const ripple = document.createElement('span');
        ripple.className = 'tk-ripple-wave';
        ripple.style.width = `${size}px`;
        ripple.style.height = `${size}px`;
        ripple.style.left = `${x}px`;
        ripple.style.top = `${y}px`;

        target.classList.add('tk-ripple-host');
        target.appendChild(ripple);

        setTimeout(() => {
            ripple.remove();
        }, 580);
    }

    document.addEventListener('pointerdown', createRipple, { passive: true });

    // =========================================================================
    // 4. UNIFIED MODAL PORTAL & GLOBAL SWEETALERT2 CONFIRMATION DIALOGS
    // =========================================================================
    function portalModalsToBody() {
        document.querySelectorAll('.modal').forEach((modal) => {
            if (modal.parentElement && modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    document.addEventListener('show.bs.modal', function (e) {
        const modal = e.target;
        if (modal && modal.classList && modal.classList.contains('modal') && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    function convertInlineConfirmForms() {
        document.querySelectorAll('form[onsubmit]').forEach((form) => {
            const rawAttr = form.getAttribute('onsubmit') || '';
            const match = rawAttr.match(/return\s+confirm\(\s*(['"`])([\s\S]*?)\1\s*\)\s*;?/i);
            if (match && match[2]) {
                form.setAttribute('data-confirm', match[2]);
                form.removeAttribute('onsubmit');
                form.onsubmit = null;
            }
        });
    }

    window.KLConfirm = async function (options = {}) {
        const title = options.title || 'Konfirmasi Tindakan';
        const text = options.text || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
        const icon = options.icon || (options.isDanger ? 'warning' : 'question');
        const confirmText = options.confirmText || (options.isDanger ? 'Ya, Lanjutkan' : 'Ya, Simpan');
        const cancelText = options.cancelText || 'Batal';
        const confirmColor = options.confirmColor || (options.isDanger ? '#dc2626' : '#14532d');

        if (typeof Swal === 'undefined') {
            return window.confirm(text);
        }

        const result = await Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#64748b',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            focusCancel: !!options.isDanger
        });

        return !!result.isConfirmed;
    };

    window.KLWhatsAppReceipt = async function (txData) {
        if (!txData) return;

        const storeInfo = window.POS_TENANT_INFO || window.KL_STORE_INFO || {};
        const storeName = txData.store_name || storeInfo.name || 'KasirLoka';
        const storeAddress = txData.store_address || storeInfo.address || '';
        const storeFooter = txData.store_footer || storeInfo.footer || 'Terima kasih atas kunjungan Anda!';

        let phone = '';
        if (typeof Swal !== 'undefined') {
            const { value: inputPhone, isConfirmed } = await Swal.fire({
                title: 'Kirim Struk Digital WhatsApp',
                html: `<div class="text-start small text-muted mb-1">Masukkan nomor WhatsApp pelanggan untuk nota <strong>${txData.transaction_number || '-'}</strong>:</div>`,
                input: 'tel',
                inputPlaceholder: 'Contoh: 081234567890',
                inputValue: txData.customer_phone || '',
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa-brands fa-whatsapp me-1"></i> Kirim Struk',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    const cleaned = String(value || '').replace(/[^0-9]/g, '');
                    if (!cleaned || cleaned.length < 9) {
                        return 'Masukkan nomor WhatsApp yang valid (minimal 9 digit).';
                    }
                    return null;
                }
            });
            if (!isConfirmed || !inputPhone) return;
            phone = String(inputPhone).replace(/[^0-9]/g, '');
        } else {
            const raw = window.prompt('Masukkan nomor WhatsApp pelanggan (cth: 081234567890):', '');
            if (!raw) return;
            phone = String(raw).replace(/[^0-9]/g, '');
        }

        if (phone.startsWith('0')) {
            phone = '62' + phone.substring(1);
        } else if (phone.startsWith('8')) {
            phone = '62' + phone;
        }

        const fmtRp = (n) => 'Rp' + new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));
        const items = Array.isArray(txData.details) ? txData.details : (Array.isArray(txData.items) ? txData.items : []);

        const lines = [];
        lines.push(`*${String(storeName).toUpperCase()}*`);
        if (storeAddress) lines.push(storeAddress);
        lines.push('--------------------------------');
        lines.push(`No. Nota : ${txData.transaction_number || '-'}`);
        lines.push(`Tanggal  : ${txData.transaction_date || '-'} ${String(txData.transaction_time || '').substring(0, 5)}`);
        if (txData.customer_name) {
            lines.push(`Pelanggan: ${txData.customer_name}`);
        }
        lines.push('--------------------------------');

        items.forEach((item) => {
            const name = item.menu_name_snapshot || item.name || 'Item';
            const qty = Number(item.quantity || item.qty || 1);
            const price = Number(item.price_snapshot || item.price || 0);
            const sub = Number(item.subtotal || (qty * price));
            lines.push(`${name}`);
            lines.push(`${qty} x ${fmtRp(price)} = *${fmtRp(sub)}*`);
        });

        lines.push('--------------------------------');
        if (Number(txData.discount_amount || 0) > 0) {
            lines.push(`Diskon   : -${fmtRp(txData.discount_amount)}`);
        }
        lines.push(`*TOTAL   : ${fmtRp(txData.total_sales)}*`);
        lines.push(`Metode   : ${String(txData.payment_method || 'TUNAI').toUpperCase()} (LUNAS)`);
        if (String(txData.payment_method || '').toLowerCase() === 'tunai' && Number(txData.cash_tendered || 0) > 0) {
            lines.push(`Tunai    : ${fmtRp(txData.cash_tendered)}`);
            lines.push(`Kembali  : ${fmtRp(txData.change_returned || 0)}`);
        }
        lines.push('--------------------------------');
        lines.push(storeFooter);

        const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(lines.join('\n'))}`;
        window.open(waUrl, '_blank', 'noopener,noreferrer');
    };

    // =========================================================================
    // 5. NAVIGATION TRANSITIONS & FORM SUBMIT FEEDBACK
    // =========================================================================
    function isInternalNavigableLink(anchor, event) {
        if (!anchor || !anchor.href) return false;
        if (event.defaultPrevented) return false;
        if (event.button && event.button !== 0) return false;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
        if (anchor.hasAttribute('download') || anchor.getAttribute('target') === '_blank') return false;
        if (anchor.hasAttribute('data-bs-toggle') || anchor.hasAttribute('data-bs-dismiss')) return false;

        const rawHref = anchor.getAttribute('href') || '';
        if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
            return false;
        }

        try {
            const url = new URL(anchor.href, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            // Same URL with only hash change
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
                return false;
            }
            return true;
        } catch (err) {
            return false;
        }
    }

    document.addEventListener('click', function (e) {
        const anchor = e.target.closest('a[href]');
        if (!anchor) return;

        if (!isInternalNavigableLink(anchor, e)) return;

        // Highlight active clicked menu item immediately for instant responsiveness
        if (anchor.classList.contains('sidebar-link') || anchor.classList.contains('submenu-link') || anchor.classList.contains('mobile-nav-item')) {
            anchor.classList.add('tk-nav-loading');
        }

        TKProgress.start();

        const mainContent = document.querySelector('.main-wrapper > .container-fluid, .pos-content-wrapper, .login-card');
        if (mainContent) {
            mainContent.classList.add('tk-page-leaving');
        }
    });

    // Form submission confirmation interceptor + loading progress & button state
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || e.defaultPrevented) return;

        // Check for inline or data-confirm attribute
        convertInlineConfirmForms();
        const confirmMsg = form.getAttribute('data-confirm');
        if (confirmMsg && form.dataset.klConfirmed !== '1') {
            e.preventDefault();
            e.stopPropagation();

            const submitter = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
            const methodInput = form.querySelector('input[name="_method"]');
            const isDelete = (methodInput && String(methodInput.value).toUpperCase() === 'DELETE')
                || /hapus|batal|suspend|reset|keluar/i.test(confirmMsg);

            window.KLConfirm({
                title: isDelete ? 'Konfirmasi Tindakan' : 'Konfirmasi Perubahan',
                text: confirmMsg,
                isDanger: isDelete,
                confirmText: isDelete ? 'Ya, Lanjutkan' : 'Ya, Lanjutkan'
            }).then((confirmed) => {
                if (confirmed) {
                    form.dataset.klConfirmed = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitter || undefined);
                    } else {
                        form.submit();
                    }
                }
            });
            return;
        }

        delete form.dataset.klConfirmed;

        if (form.getAttribute('target') === '_blank') return;

        TKProgress.start();

        // Add subtle loading spinner to the submitter button unless opted out
        const submitter = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitter && !submitter.hasAttribute('data-no-loading') && submitter.tagName === 'BUTTON' && !submitter.classList.contains('tk-btn-submitting')) {
            submitter.classList.add('tk-btn-submitting');
            const width = submitter.offsetWidth;
            if (width > 0) {
                submitter.style.minWidth = `${width}px`;
            }
        }
    });

    // Restore state when navigating back via browser BFcache
    window.addEventListener('pageshow', function () {
        TKProgress.done();
        document.querySelectorAll('.tk-page-leaving').forEach(el => el.classList.remove('tk-page-leaving'));
        document.querySelectorAll('.tk-nav-loading').forEach(el => el.classList.remove('tk-nav-loading'));
        document.querySelectorAll('.tk-btn-submitting').forEach(el => el.classList.remove('tk-btn-submitting'));
    });

    // =========================================================================
    // 6. STAGGERED ENTRANCE & MENU OPENING ANIMATIONS
    // =========================================================================
    function applyStaggeredAnimations() {
        portalModalsToBody();
        convertInlineConfirmForms();

        // Stagger Dashboard & Page Cards, then remove animation class on end so no transform containing block persists
        const cards = document.querySelectorAll('.premium-card, .card:not(.pos-product-card)');
        cards.forEach((card, index) => {
            card.style.setProperty('--tk-stagger', Math.min(index, 10));
            card.classList.add('tk-stagger-item');
            card.addEventListener('animationend', function () {
                card.classList.remove('tk-stagger-item');
            }, { once: true });
        });

        // Stagger Table Rows (capped at first 15 rows for 60fps smoothness)
        const rows = document.querySelectorAll('.table tbody tr');
        rows.forEach((row, index) => {
            if (index < 15) {
                row.style.setProperty('--tk-stagger', index);
                row.classList.add('tk-stagger-row');
                row.addEventListener('animationend', function () {
                    row.classList.remove('tk-stagger-row');
                }, { once: true });
            }
        });

        // Stagger POS Product Cards on initial load
        const posCards = document.querySelectorAll('#posProductGrid .pos-product-card');
        posCards.forEach((card, index) => {
            card.style.setProperty('--tk-stagger', Math.min(index, 16));
            card.classList.add('tk-pos-card-enter');
        });

        // Stagger Sidebar Menu Items on initial load
        const sidebarItems = document.querySelectorAll('#sidebar .sidebar-menu > .sidebar-item');
        sidebarItems.forEach((item, index) => {
            item.style.setProperty('--tk-stagger', index);
            item.classList.add('tk-sidebar-item-enter');
        });

        // Mobile Offcanvas Drawer Stagger when opened
        const offcanvasEl = document.getElementById('sidebarOffcanvas');
        if (offcanvasEl) {
            offcanvasEl.addEventListener('show.bs.offcanvas', function () {
                const items = offcanvasEl.querySelectorAll('.sidebar-menu > .sidebar-item');
                items.forEach((item, idx) => {
                    item.style.setProperty('--tk-stagger', idx);
                    item.classList.remove('tk-drawer-item-reveal');
                    void item.offsetWidth; // trigger reflow
                    item.classList.add('tk-drawer-item-reveal');
                });
            });
        }

        // Submenu Collapse Smooth Stagger when opened
        const submenus = document.querySelectorAll('#settingsSubmenuDesktop, #settingsSubmenuMobile');
        submenus.forEach(submenu => {
            submenu.addEventListener('show.bs.collapse', function () {
                const items = submenu.querySelectorAll('.submenu-item');
                items.forEach((item, idx) => {
                    item.style.setProperty('--tk-stagger', idx);
                    item.classList.remove('tk-submenu-item-reveal');
                    void item.offsetWidth;
                    item.classList.add('tk-submenu-item-reveal');
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyStaggeredAnimations);
    } else {
        applyStaggeredAnimations();
    }
})();

