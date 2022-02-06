importScripts('https://cdnjs.cloudflare.com/ajax/libs/firebase/8.10.1/firebase.js');
importScripts('https://cdnjs.cloudflare.com/ajax/libs/firebase/8.10.1/firebase-messaging.min.js');

const firebaseConfig = {

    apiKey: "AIzaSyBE90OnfKk6MELQ2-Z_DVLYp4maXVzvQO8",

    authDomain: "ninja-firestore-tut-nov11.firebaseapp.com",

    databaseURL: "https://ninja-firestore-tut-nov11-default-rtdb.asia-southeast1.firebasedatabase.app",

    projectId: "ninja-firestore-tut-nov11",

    storageBucket: "ninja-firestore-tut-nov11.appspot.com",

    messagingSenderId: "28680409583",

    appId: "1:28680409583:web:66bef6c94232b88816e61a"

};
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();
messaging.onBackgroundMessage((payload) => {
    console.log('onBackgroundMessage ', payload);
    // Customize notification here
    // const {title, body} = payload.notification;
    // const notificationTitle = title;
    // const notificationOptions = {
    //     body: body,
    //     icon: '/firebase-logo.png'
    // };
    // self.registration.showNotification(notificationTitle,
    //     notificationOptions);



});

// self.addEventListener('notificationclick', function (event) {
//     console.log('On notification click: ', event.notification);
//     event.notification.close();

//     // This looks to see if the current is already open and
//     // focuses if it is
//     event.waitUntil(clients.matchAll({
//         type: "window"
//     }).then(function (clientList) {
//         console.log(clientList)
//         for (var i = 0; i < clientList.length; i++) {
//             var client = clientList[i];
//             if (client.url == '/dashboard' && 'focus' in client)
//                 return client.focus();
//         }
//         if (clients.openWindow)
//             return clients.openWindow('/dashboard');
//     }));
// });

/**
 * User resources
 */
//https://developers.google.com/web/updates/2015/05/notifying-you-of-changes-to-notifications
//https://developers.google.com/web/fundamentals/push-notifications/how-push-works