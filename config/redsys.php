<?php

return [
    /*
    | Datos del comercio que facilita el banco (Canales / portal de administración de Redsys).
    | Los valores de pruebas por defecto son el comercio público de test de Redsys.
    */
    'merchant_code' => env('REDSYS_MERCHANT_CODE', '999008881'),
    'terminal' => env('REDSYS_TERMINAL', '1'),
    'secret_key' => env('REDSYS_KEY', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'),

    // 'test' o 'production'
    'environment' => env('REDSYS_ENV', 'test'),

    // ISO 4217: 'EUR' o '978'
    'currency' => env('REDSYS_CURRENCY', 'EUR'),

    // 'es', 'en', 'ca'… o null para que Redsys use el idioma del navegador
    'language' => env('REDSYS_LANGUAGE'),

    'merchant_name' => env('REDSYS_MERCHANT_NAME', env('APP_NAME')),

    // URLs por defecto; se pueden sobrescribir en cada pago
    'notification_url' => env('REDSYS_URL_NOTIFICATION'),
    'ok_url' => env('REDSYS_URL_OK'),
    'ko_url' => env('REDSYS_URL_KO'),
];
