<?php 

return [
    'enabled' => env('TRELLOG_ENABLED', true),
    'apikey' => env('TRELLOG_APIKEY'),
    'token' => env('TRELLOG_TOKEN'),
    'list_errors' => env('TRELLOG_LIST_ERRORS'),
    'queue' => env('TRELLOG_QUEUE'),
    'cooldown' => env('TRELLOG_COOLDOWN', false) //Cooldown of a message with the same fingerprint in minutes
];