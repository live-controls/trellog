<?php 

return [
    'apikey' => env('TRELLOG_APIKEY'),
    'token' => env('TRELLOG_TOKEN'),
    'list_errors' => env('TRELLOG_LIST_ERRORS'),
    'queue' => env('TRELLOG_QUEUE'),
    'cooldown' => 2 //Cooldown of a message with the same fingerprint in minutes
];