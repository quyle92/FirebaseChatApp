<?php

return [
    'driver' => env('FCM_PROTOCOL', 'http'),
    'log_enabled' => false,

    'http' => [
        'server_key' => env('FCM_SERVER_KEY', 'Your FCM server key'),
        'sender_id' => env('FCM_SENDER_ID', 'Your sender id'),
        'server_send_url' => 'https://fcm.googleapis.com/fcm/send',
        'server_group_url' => 'https://android.googleapis.com/gcm/notification',
        'timeout' => 30.0, // in second
    ],

    'VAPID' => env('VAPID', '')
];
//server_key vs VAPID: https://docs.kony.com/konylibrary/messaging/kms_console_user_guide/Content/Apps/Generating_Web_FCM_keys.htm (vapid is "p256dh" or "subscription public key" which is referred in https://developers.google.com/web/fundamentals/push-notifications/web-push-protocol )
