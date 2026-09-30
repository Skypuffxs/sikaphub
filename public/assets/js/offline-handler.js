/**
 * S.I.K.A.P. Hub - Silent Service Worker Registration
 * Enables complete offline availability without intrusive UI popups.
 */

(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            var isSubfolder = window.location.pathname.startsWith('/sikaphub');
            var pathPrefix = isSubfolder ? '/' : '/';
            var swUrl = isSubfolder ? '/sw.js' : '/sw.js';

            navigator.serviceWorker.register(swUrl, { scope: pathPrefix })
                .then(function (registration) {
                    console.log('[Offline Engine] ServiceWorker ready for offline browsing.');
                })
                .catch(function (error) {
                    console.warn('[Offline Engine] ServiceWorker registration error:', error);
                });
        });
    }
})();
