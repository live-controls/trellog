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