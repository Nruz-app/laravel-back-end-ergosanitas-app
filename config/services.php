<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // SPEC 03 — Mantenedor de imagenes del Home. URL publica de la carpeta public/HomeImagenes,
    // sin barra final. HomeImagen::src la usa para armar la URL de cada imagen al responder.
    'home_imagenes' => [
        'url' => rtrim((string) env('API_PATH_HOME', ''), '/'),
    ],

    // SPEC 04 — Carro de servicios (servicio-cart-flow). Reutiliza las credenciales de Webpay del
    // flujo anterior; la URL de retorno es propia (apunta a api/cart-flow/webpay/retorno) y
    // front_url es donde se redirige al cliente despues de pagar (/servicios/resultado).
    'cart_flow' => [
        'webpay_url' => env('WEBPAY_URL'),
        'webpay_id' => env('WEBPAY_ID'),
        'webpay_secret' => env('WEBPAY_SECRET'),
        'webpay_return' => env('WEBPAY_CART_FLOW_RETURN'),
        'front_url' => rtrim((string) env('CART_FLOW_FRONT_URL', 'https://ergosanitas.com'), '/'),
    ],

];
