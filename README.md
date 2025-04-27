# Trellog
Logger for Laravel to Trello

### Env variables
TRELLOG_KEY => Trello Api Key
TRELLOG_TOKEN => Trello Api Token
TRELLOG_LIST_ERRORS => List to add errors

### Usage
Add to bootstrap/app.php:
```
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (\Throwable $e) {
        SendTrellog::dispatch($e);
    });
})
```

### Configuration
- 'apikey': Your Trello API Key
- 'token': Your Trello API tokenn
- 'list_errors' => The Id of the list you want to upload the errors to. This can be found by https://trello.com/b/LINK_TO_YOUR_BOARD.json
- 'queue' => The queue TRELLOG will be running on
- 'cooldown' => The cooldown in minutes before a report with the same fingerprint is uploaded again. If set to FALSE this feature will be disabled. Important to know that this only works with Cache that support TTL!