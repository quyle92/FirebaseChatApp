importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js');

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
    console.log('[firebase-messaging-sw.js] Received background message ', payload);
    // Customize notification here
    const {title, body} = payload.notification;
    const notificationTitle = title;
    const notificationOptions = {
        body: body,
        icon: '/firebase-logo.png'
    };
    self.addEventListener('push', function (event) {
        console.log('push')
        event.waitUntil(
            self.registration.showNotification(notificationTitle,
                notificationOptions)
        )
    })
});