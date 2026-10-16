{{--
    Page loader. Navigating (internal link, form, refresh) shows a thin progress bar at the top right away;
    the blurred overlay with the company logo only appears if the next page takes more than 1.2 s.
    A page that is opening is never covered (it used to stay blurred until every script was loaded). Self-contained (styles + script) so it also works on the login pages.
    Links or forms can opt out with data-no-loader. Downloads (PDF, exports) never show it.
--}}
<div id="erp-page-progress" class="erp-page-progress" aria-hidden="true"></div>
<div id="erp-page-loader" class="erp-page-loader" role="status" aria-live="polite" aria-busy="false">
    <div class="erp-page-loader__box">
        <div class="erp-page-loader__logo">
            <x-brand-logo class="erp-page-loader__img" :show-name="false" />
        </div>
        <div class="erp-page-loader__spinner" aria-hidden="true"></div>
        <span class="erp-page-loader__text">Chargement...</span>
    </div>
</div>
<noscript><style>#erp-page-loader, #erp-page-progress { display: none !important; }</style></noscript>

<style>
    .erp-page-progress {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 10001;
        height: 3px;
        width: 0;
        background: #1f5fae;
        box-shadow: 0 0 6px rgba(31, 95, 174, .5);
        opacity: 0;
        transition: width .2s ease, opacity .3s ease;
    }

    .erp-page-progress.is-active {
        width: 85%;
        opacity: 1;
        transition: width 2.5s cubic-bezier(.1, .7, .2, 1), opacity .1s ease;
    }

    .erp-page-loader {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(244, 246, 250, .55);
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
        opacity: 0;
        visibility: hidden;
        transition: opacity .18s ease, visibility 0s linear .18s;
    }

    .erp-page-loader.is-active {
        opacity: 1;
        visibility: visible;
        transition: opacity .18s ease;
    }

    .erp-page-loader__box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
    }

    .erp-page-loader__logo {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 96px;
        height: 96px;
        padding: 12px;
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 12px 32px rgba(16, 24, 40, .14);
        animation: erp-loader-pulse 1.4s ease-in-out infinite;
    }

    .erp-page-loader__logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* Wide colour logo: wider tile */
    .erp-page-loader__logo:has(img) {
        width: 190px;
        height: 110px;
    }

    /* Without a configured logo: the initials badge fills the tile */
    .erp-page-loader__logo .erp-brand-text {
        display: flex;
    }

    .erp-page-loader__logo .erp-brand-initials {
        width: 72px;
        height: 72px;
        border-radius: 18px;
        font-size: 26px;
    }

    .erp-page-loader__spinner {
        width: 28px;
        height: 28px;
        border: 3px solid rgba(31, 95, 174, .18);
        border-top-color: #1f5fae;
        border-radius: 50%;
        animation: erp-loader-spin .8s linear infinite;
    }

    .erp-page-loader__text {
        font-size: 13px;
        font-weight: 600;
        color: #5b6b7f;
        letter-spacing: .2px;
    }

    @keyframes erp-loader-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes erp-loader-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    @media (prefers-reduced-motion: reduce) {
        .erp-page-loader__logo,
        .erp-page-loader__spinner {
            animation-duration: 3s;
        }
    }
</style>

<script>
    (function () {
        'use strict';

        var loader = document.getElementById('erp-page-loader');
        if (!loader) {
            return;
        }

        var progress = document.getElementById('erp-page-progress');
        var SAFETY_MS = 10000;      // never leave the overlay on screen longer than this
        var OVERLAY_DELAY_MS = 1200; // fast pages only show the progress bar
        var safetyTimer = null;
        var overlayTimer = null;
        var skipNextUnload = false; // set for downloads, which fire beforeunload but never leave the page

        // URLs that download a file instead of opening a page
        var DOWNLOAD = /(\/pdf(\?|$)|\/excel(\?|$)|products-export|export_invoices|\/download\/|export=csv)/i;

        function show() {
            if (progress) {
                progress.classList.add('is-active');
            }
            clearTimeout(overlayTimer);
            overlayTimer = setTimeout(function () {
                loader.classList.add('is-active');
                loader.setAttribute('aria-busy', 'true');
            }, OVERLAY_DELAY_MS);
            clearTimeout(safetyTimer);
            safetyTimer = setTimeout(hide, SAFETY_MS);
        }

        function hide() {
            if (progress) {
                progress.classList.remove('is-active');
            }
            loader.classList.remove('is-active');
            loader.setAttribute('aria-busy', 'false');
            clearTimeout(overlayTimer);
            clearTimeout(safetyTimer);
        }

        function isDownload(url) {
            return DOWNLOAD.test(url);
        }

        // Page ready: remove the overlay shown during the load / refresh
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hide);
        } else {
            hide();
        }

        // Back / forward cache restores the page with the overlay still visible
        window.addEventListener('pageshow', hide);

        // Internal links
        document.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;
            if (!link || event.defaultPrevented) {
                return;
            }
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return; // new tab / window
            }

            var href = link.getAttribute('href') || '';
            if (href === '' || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) {
                return;
            }
            if (link.target && link.target !== '_self') {
                return;
            }
            if (link.hasAttribute('download') || link.hasAttribute('data-no-loader') || link.hasAttribute('data-toggle')) {
                return;
            }
            if (link.origin && link.origin !== window.location.origin) {
                return;
            }
            if (isDownload(link.href)) {
                skipNextUnload = true;
                setTimeout(function () { skipNextUnload = false; }, 1500);
                return;
            }
            // Same page anchor (only the hash changes)
            if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) {
                return;
            }

            show();
        });

        // Forms: after confirm() / validation handlers had their say (bubbling phase)
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (event.defaultPrevented || form.hasAttribute('data-no-loader')) {
                return;
            }
            if (form.target && form.target !== '_self') {
                return;
            }
            if (isDownload(form.action || '')) {
                return;
            }
            show();
        });

        // Browser refresh / address bar navigation
        window.addEventListener('beforeunload', function () {
            if (skipNextUnload) {
                skipNextUnload = false;
                return;
            }
            show();
        });

        // Expose for pages that navigate in JavaScript
        window.ErpPageLoader = { show: show, hide: hide };
    })();
</script>
