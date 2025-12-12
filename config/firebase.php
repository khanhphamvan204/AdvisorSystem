
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Path to your Firebase service account JSON file
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', base_path('storage/app/firebase/service-account.json')),

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | Your Firebase project ID
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Database URL
    |--------------------------------------------------------------------------
    |
    | Your Firebase Realtime Database URL (optional, only if using Realtime DB)
    |
    */
    'database_url' => env('FIREBASE_DATABASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Default FCM Device Token
    |--------------------------------------------------------------------------
    |
    | Default FCM token for testing/development. 
    | In production, tokens should be stored in database per user.
    |
    */
    'default_fcm_token' => env('FCM_TOKEN'),
];
