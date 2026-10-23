<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Aplikasi hanya memakai dua guard: `staff` (sesi server-side) dan
    | `member` (Bearer JWT). Tidak ada password broker.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'staff'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | `staff` memakai session driver (Redis) dengan masa 8 jam.
    | `member` memakai driver `jwt` yang didaftarkan di AppServiceProvider.
    |
    */

    'guards' => [
        'staff' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'member' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Member JWT
    |--------------------------------------------------------------------------
    |
    | Path relatif terhadap base_path. Kunci tidak masuk repo (lihat
    | .gitignore) dan dibuat per environment.
    |
    */

    'jwt' => [
        'private_key_path' => base_path(env('JWT_PRIVATE_KEY_PATH', 'storage/app/keys/jwt-private.pem')),
        'public_key_path' => base_path(env('JWT_PUBLIC_KEY_PATH', 'storage/app/keys/jwt-public.pem')),
        'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),
    ],

];
