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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercadopago' => [
        'token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

        // A aplicação da PLATAFORMA (conta do Sidney), para o organizador
        // conectar a conta dele por OAuth e a taxa ir no application_fee
        // (ADR 0008). Sem client_id, a tela "Cobrança" só explica que falta
        // configurar.
        'app' => [
            'client_id' => env('MERCADOPAGO_APP_CLIENT_ID'),
            'client_secret' => env('MERCADOPAGO_APP_CLIENT_SECRET'),
            'redirect_uri' => env('MERCADOPAGO_OAUTH_REDIRECT_URI'),
            'webhook_secret' => env('MERCADOPAGO_APP_WEBHOOK_SECRET'),
        ],
    ],

    // Para onde vai o alerta de cobrança parada (token que não renovou, Pix
    // que não saiu com conta conectada). Ver App\Services\Cobranca\AlertaDeCobranca.
    'alertas' => [
        'cobranca_email' => env('ALERTA_COBRANCA_EMAIL'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

];
