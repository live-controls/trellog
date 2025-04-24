<?php

namespace LiveControls\Trellog;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Throwable;

class Trellog
{
    public static function error(FlattenException $exception): bool
    {
        try{
            //Load config variables
            $apiKey = config('trellog.apikey');
            $token = config('trellog.token');
            $listId = config('trellog.list_errors');

            //Fetch exception informations
            $class = $exception->getClass();
            $shortClass = class_basename($class);
            $message = $exception->getMessage();
            $file = $exception->getFile();
            $line = $exception->getLine();
            $operationMode = (app()->hasDebugModeEnabled() ? "DEV" : "PRO");
            //Generate Fingerprint
            $fingerprint = hash('sha256', "{$class}|{$message}|{$file}|{$line}|{$operationMode}");

            //Generate Title
            $title = "[{$fingerprint}] {$shortClass}: {$message} - {$operationMode}";

            $client = new Client();

            //Search for existing error log
            $query = urlencode($fingerprint);
            $searchUrl = "https://api.trello.com/1/search?query={$query}&modelTypes=cards&card_fields=name,idList,desc&cards_limit=10&key={$apiKey}&token={$token}";
            $response = $client->get($searchUrl);
            $searchResult = json_decode($response->getBody()->getContents(), true);

            foreach ($searchResult['cards'] ?? [] as $card){
                if (strpos($card['name'], $fingerprint) !== false){
                    //Card found, move if necessary
                    if ($card['idList'] !== $listId){
                        $moveUrl = "https://api.trello.com/1/cards/{$card['id']}/idList?key={$apiKey}&token={$token}";
                        $moveResponse = $client->put($moveUrl, [
                            'form_params' => [
                                'value' => $listId, // The new list ID
                            ],
                        ]);
                        if($moveResponse->getStatusCode() !== 200)
                        {
                            throw new Exception("Couldn't move Trello Card");
                        }
                    }

                    //Increment amount or append to end if it doesn't exist
                    $desc = $card['desc'];
                    $matches = [];
                    $amount = 1;

                    if(preg_match('/\*\*Amount:\*\* (\d+)/', $desc, $matches)){
                        $amount = (int)$matches[1] + 1;
                        $desc = preg_replace('/\*\*Amount:\*\* \d+/', "**Amount:** {$amount}", $desc);
                    }else{
                        $desc .= "\n\n**Amount:** {$amount}";
                    }

                    // Update the card description
                    $updateUrl = "https://api.trello.com/1/cards/{$card['id']}?" . http_build_query([
                        'key' => $apiKey,
                        'token' => $token,
                        'desc' => $desc,
                    ]);
                    $updateResponse = $client->put($updateUrl);
                    return $updateResponse->getStatusCode() == 200;
                }
            }

            //If it does not exist, create a new card with the informations and amount set to 1
            $description = implode("\n", [
                "**Production:** ".(app()->hasDebugModeEnabled() ? "Yes" : "No"),
                "**Exception:** $shortClass",
                "**Message:** $message",
                "**Code:** " . $exception->getCode(),
                "**File:** $file",
                "**Line:** $line",
                "",
                "**Stack trace:**",
                "```",
                $exception->getTraceAsString(),
                "```",
                "",
                "**Fingerprint:** $fingerprint",
                "**Amount:** 1"
            ]);

            $createUrl = 'https://api.trello.com/1/cards';
            $params = [
                'key'   => $apiKey,
                'token' => $token,
                'idList' => $listId,
                'name'  => $title,
                'desc'  => $description,
            ];

            $createResponse = $client->post($createUrl, [
                'form_params' => $params,
            ]);
            return $createResponse->getStatusCode() == 201;
        }catch(\Throwable $ex)
        {
            Log::error("[TRELLOG] ".$ex->getMessage(), [
                'exeption' => $ex
            ]);
            return false;
        }
    }
}
