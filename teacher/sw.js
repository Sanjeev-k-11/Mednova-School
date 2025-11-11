// sw.js

self.addEventListener('push', function(event) {
    const data = event.data.json(); // The data comes from our server

    const title = data.title;
    const options = {
        body: data.body,
        icon: data.icon || 'default-icon.png', // A default icon
        badge: 'badge-icon.png', // An icon for the notification bar on mobile
        data: {
            url: data.url // URL to open when notification is clicked
        }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    // Close the notification
    event.notification.close();

    // Open the chat window or focus it if it's already open
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(clientsArr => {
            const hadWindowToFocus = clientsArr.some(windowClient => 
                windowClient.url === event.notification.data.url ? (windowClient.focus(), true) : false
            );

            if (!hadWindowToFocus) {
                clients.openWindow(event.notification.data.url).then(windowClient => windowClient ? windowClient.focus() : null);
            }
        })
    );
});