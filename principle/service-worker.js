// service-worker.js
// This file must be in the root of your domain for push notifications to work correctly.

self.addEventListener('push', function(event) {
    const data = event.data.json();
    console.log('Push received:', data);

    const title = data.title || 'New Message';
    const options = {
        body: data.body || 'You have a new message.',
        icon: data.icon || '../assets/images/school_logo.png', // Adjust path to your school logo
        badge: data.badge || '../assets/images/badge.png', // Optional badge icon
        data: {
            url: data.url || 'principal_chat.php' // URL to open when notification is clicked
        }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    console.log('Notification clicked:', event.notification.data.url);
    event.notification.close();

    // This looks for an existing window/tab and focuses it, or opens a new one
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function(clientList) {
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url.includes(event.notification.data.url) && 'focus' in client) {
                        return client.focus();
                    }
                }
                // If no matching window found, open a new one
                if (clients.openWindow) {
                    return clients.openWindow(event.notification.data.url);
                }
            })
    );
});