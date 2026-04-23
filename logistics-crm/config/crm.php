<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External API token (X-API-TOKEN)
    |--------------------------------------------------------------------------
    |
    | Machine clients send this value in the X-API-TOKEN header. Keep it long,
    | random, and rotate via your secrets manager. Never commit real tokens.
    |
    */
    'external_api' => [
        'token' => env('EXTERNAL_API_TOKEN', ''),
    ],

];
