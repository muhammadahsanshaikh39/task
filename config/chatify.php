<?php

return [
    /*
    |-------------------------------------
    | Messenger display name
    |-------------------------------------
    */
    'name' => '',

    /*
    |-------------------------------------
    | The disk on which to store added
    | files and derived images by default.
    |-------------------------------------
    */
    'storage_disk_name' => env('CHATIFY_STORAGE_DISK', 'public'),

    /*
    |-------------------------------------
    | Routes configurations
    |-------------------------------------
    */
    'routes' => [
        'prefix' => env('CHATIFY_ROUTES_PREFIX', 'chatify'),
        'middleware' => env('CHATIFY_ROUTES_MIDDLEWARE', ['web', 'auth', 'has_workspace']),
        'namespace' => env('CHATIFY_ROUTES_NAMESPACE', 'App\Http\Controllers\vendor\Chatify'),
    ],
    'api_routes' => [
        'prefix' => env('CHATIFY_API_ROUTES_PREFIX', 'chatify/api'),
        'middleware' => env('CHATIFY_API_ROUTES_MIDDLEWARE', ['api']),
        'namespace' => env('CHATIFY_API_ROUTES_NAMESPACE', 'App\Http\Controllers\vendor\Chatify\Api'),
    ],

    /*
    |-------------------------------------
    | Pusher API credentials
    |-------------------------------------
    */
    'pusher' => [
        'debug' => env('APP_DEBUG', false),
        'key' => '',
        'secret' => '',
        'app_id' => '',
        'options' => [
            'cluster' => '',
            'encrypted' => true,
        ],
    ],

    /*
    |-------------------------------------
    | User Avatar
    |-------------------------------------
    */
    'user_avatar' => [
        'folder' => 'users-avatar',
        'default' => 'avatar.png',
    ],

    /*
    |-------------------------------------
    | Gravatar
    |
    | imageset property options:
    | [ 404 | mp | identicon (default) | monsterid | wavatar ]
    |-------------------------------------
    */
    'gravatar' => [
        'enabled' => true,
        'image_size' => 200,
        'imageset' => 'identicon'
    ],

    /*
    |-------------------------------------
    | Attachments
    |-------------------------------------
    */
    'attachments' => [
        'folder' => 'attachments',
        'download_route_name' => 'attachments.download',
        'allowed_images' => (array) ['png', 'jpg', 'jpeg', 'gif'],
        'allowed_files' => (array) ['zip', 'rar', 'txt'],
        'max_upload_size' => env('CHATIFY_MAX_FILE_SIZE', 150), // MB
    ],

    /*
    |-------------------------------------
    | Messenger's colors
    |-------------------------------------
    */
    'colors' => (array) [
        '#2180f3',
        '#2196F3',
        '#00BCD4',
        '#3F51B5',
        '#673AB7',
        '#4CAF50',
        '#FFC107',
        '#FF9800',
        '#ff2522',
        '#9C27B0',
    ],
    /*
    |-------------------------------------
    | Sounds
    | You can enable/disable the sounds and
    | change sound's name/path placed at
    | `public/` directory of your app.
    |
    |-------------------------------------
    */
    'sounds' => [
        'enabled' => true,
        'public_path' => 'sounds/chatify',
        'new_message' => 'new-message-sound.mp3',
    ]
];
