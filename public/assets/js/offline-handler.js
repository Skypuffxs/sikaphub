/**
 * S.I.K.A.P. Hub - Silent Service Worker Registration
 * Enables complete offline availability without intrusive UI popups.
 */

(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sikaphub/sw.js', { scope: '/sikaphub/' })
                .then(function (registration) {
                    console.log('[Offline Engine] ServiceWorker ready for offline browsing.');
                })
                .catch(function (error) {
                    console.warn('[Offline Engine] ServiceWorker registration error:', error);
                });
        });
    }
})();
