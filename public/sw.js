// QuakeLogic Proposals service worker.
// Minimal: enables PWA installability and routes notification clicks.
const VERSION = 'ql-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Network passthrough (required for installability; no aggressive caching so the
// app always serves fresh content).
self.addEventListener('fetch', () => { /* default network behaviour */ });

// Allow the page to trigger a notification from the SW (so it works while the
// app is backgrounded / installed).
self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type === 'notify' && self.registration.showNotification) {
        self.registration.showNotification(data.title || 'Notification', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: data.tag,
            data: { url: data.url || '/' },
        });
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((list) => {
            for (const client of list) {
                if ('focus' in client) { client.navigate(url); return client.focus(); }
            }
            if (self.clients.openWindow) return self.clients.openWindow(url);
        })
    );
});
