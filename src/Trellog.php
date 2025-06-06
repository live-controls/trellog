<?php

namespace LiveControls\Trellog;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Throwable;
use Illuminate\Support\Str;

class Trellog
{
    public static function error(FlattenException $exception): bool
    {
        if(config('trellog.enabled', true) == false){
            //Returns true if trellog is disabled as it can be ignored
            return true;
        }

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

            //Only call the cooldown if it is set and NOT false
            $cooldown = (int)config('trellog.cooldown', false);
            if(is_int($cooldown) && $cooldown != 0){
                if(Cache::get("sent_report_{$fingerprint}", false)){
                    return true; //Can return, because the same report was already sent within the last trellog.cooldown minutes
                }
                Cache::put("sent_report_{$fingerprint}", true, now()->addMinutes($cooldown));
            }

            $titleMessage = Str::limit($message, 50, '...', true);
            //Generate Title
            $title = "{$shortClass}: {$titleMessage} - {$operationMode}";

            $client = new Client();

            //Search for existing error log
            $query = urlencode($fingerprint);
            $searchUrl = "https://api.trello.com/1/search?query={$query}&modelTypes=cards&card_fields=idList,desc&cards_limit=10&key={$apiKey}&token={$token}";
            $response = $client->get($searchUrl);
            $searchResult = json_decode($response->getBody()->getContents(), true);

            foreach ($searchResult['cards'] ?? [] as $card){
                if (strpos($card['desc'], $fingerprint) !== false){
                    //Card found, move if necessary
                    if ($card['idList'] !== $listId){
                        $moveUrl = "https://api.trello.com/1/cards/{$card['id']}/idList?key={$apiKey}&token={$token}";
                        $moveResponse = $client->put($moveUrl, [
                            'json' => [
                                'value' => $listId, //The new list ID
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

                    //Update total reports
                    if(preg_match('/\*\*Total Reports:\*\* (\d+)/', $desc, $matches)){
                        $amount = (int)$matches[1] + 1;
                        $desc = preg_replace('/\*\*Total Reports:\*\* \d+/', "**Total Reports:** {$amount}", $desc);
                    }else{
                        $desc .= "\n\n**Total Reports:** {$amount}";
                    }

                    //Update latest report
                    $latestReport = now()->format('d/m/Y H:i:s');
                    if (preg_match('/\*\*Latest Report:\*\* .+/', $desc)) {
                        $desc = preg_replace('/\*\*Latest Report:\*\* .+/', "**Latest Report:** {$latestReport}", $desc);
                    } else {
                        $desc .= "\n**Latest Report:** {$latestReport}";
                    }

                    //Update latest version
                    $latestVersion = config('app.version', 'Unknown'); // Replace this with your actual version dynamically
                    if (preg_match('/\*\*Latest Version:\*\* .+/', $desc)) {
                        $desc = preg_replace('/\*\*Latest Version:\*\* .+/', "**Latest Version:** {$latestVersion}", $desc);
                    } else {
                        $desc .= "\n**Latest Version:** {$latestVersion}";
                    }
                    // Update the card description
                    $updateUrl = "https://api.trello.com/1/cards/{$card['id']}?" . http_build_query([
                        'key' => $apiKey,
                        'token' => $token,
                        'desc' => $desc,
                    ]);

                    $client->request('POST', "https://api.trello.com/1/cards/{$card['id']}/attachments", [
                        'multipart' => [
                            [
                                'name'     => 'file',
                                'contents' => $exception->getTraceAsString(),
                                'filename' => "stacktrace_".now()->timestamp."_{$amount}.txt",
                            ],
                            [
                                'name'     => 'key',
                                'contents' => $apiKey,
                            ],
                            [
                                'name'     => 'token',
                                'contents' => $token,
                            ],
                            [
                                'name'     => 'name',
                                'contents' => 'Stack Trace',
                            ],
                        ]
                    ]);

                    $updateResponse = $client->put($updateUrl);
                    return $updateResponse->getStatusCode() == 200;
                }
            }

            //If it does not exist, create a new card with the informations and amount set to 1
            $description = implode("\n", [
                "**User:** ".(Auth::id() ?? 'None'),
                "**Operation Mode:** $operationMode",
                "**Exception:** $shortClass",
                "**Message:** $message",
                "**Code:** " . $exception->getCode(),
                "**File:** $file",
                "**Line:** $line",
                "**Fingerprint:** $fingerprint",
                "**Total Reports:** 1",
                "**Latest Report:** ".now()->format('d/m/Y H:i:s'),
                "**Latest Version:** ".config('app.version', 'Unknown'),
            ]);

            $createUrl = 'https://api.trello.com/1/cards';
            $params = [
                'key'   => $apiKey,
                'token' => $token,
                'idList' => $listId,
                'name'  => $title,
                'desc'  => $description,
            ];

            if(app()->hasDebugModeEnabled()){
                Log::debug($params);
            }

            $createResponse = $client->post($createUrl, [
                'json' => $params,
            ]);
            $createdCard = json_decode($createResponse->getBody()->getContents(), true);
            $cardId = $createdCard['id'] ?? null;

            //Upload Stacktrace as attachment
            $client->request('POST', "https://api.trello.com/1/cards/{$cardId}/attachments", [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => $exception->getTraceAsString(),
                        'filename' => "stacktrace_".now()->timestamp.".txt",
                    ],
                    [
                        'name'     => 'key',
                        'contents' => $apiKey,
                    ],
                    [
                        'name'     => 'token',
                        'contents' => $token,
                    ],
                    [
                        'name'     => 'name',
                        'contents' => 'Stack Trace',
                    ],
                ]
            ]);
        }catch(\Throwable $ex)
        {
            Log::error("[TRELLOG] ".$ex->getMessage(), [
                'exeption' => $ex
            ]);
            return false;
        }
        return true;
    }
}
