<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\TelegramController;
use App\Models\Telegram;


class TrelloController extends Controller
{

    protected $telegramController;

    public function __construct(TelegramController $telegramController)
    {
        $this->telegramController = $telegramController;
    }

        public function index(){

            return;
        }


        public static function getUserBoards($accessToken){
            $response = Http::get("https://api.trello.com/1/members/me/boards", [
                'key' => env('TRELLO_API_KEY'),
                'token' => $accessToken,
            ]);
            
            return $response->json();
        }

        public static function getBoardLink($boardId, $accessToken){
            $response = Http::get("https://api.trello.com/1/boards/{$boardId}", [
                'key' => env('TRELLO_API_KEY'),
                'token' => $accessToken,
            ]);
            
            if ($response->successful()) {
                $board = $response->json();
                return $board['url']; 
            }

            return null;
        }


        public function getBoardCards($boardId, $accessToken)
        {
            $response = Http::get("https://api.trello.com/1/boards/{$boardId}/cards", [
                'key' => env('TRELLO_API_KEY'),
                'token' => $accessToken,
            ]);
    
            return $response->json();
        }

        public function getCardDetails($cardId, $accessToken)
        {
            $response = Http::get("https://api.trello.com/1/cards/{$cardId}", [
                'key' => env('TRELLO_API_KEY'),
                'token' => $accessToken,
            ]);
    
            return $response->json();
        }
    
        public function getUserCards($accessToken)
        {
            $boards = $this->getUserBoards($accessToken);
            $userCards = [];
    
            foreach ($boards as $board) {
                $cards = $this->getBoardCards($board['id'], $accessToken);
    
                foreach ($cards as $card) {
                    if (in_array($accessToken, $card['idMembers'])) {
                        $userCards[] = $card;
                    }
                }
            }
    
            return $userCards;
        }
    

     /**
    * Обробка Webhook Trello
    */
    public function webhook(Request $request){
        $action = $request->input('action');
        if ($action && $action['type'] == 'updateCard') {
            $cardName = $action['data']['card']['name'];
            $fromList = $action['data']['listBefore']['name'];
            $toList = $action['data']['listAfter']['name'];
            $message = '';
            $message .= 'Зміна картки: ' . $cardName . PHP_EOL;
            $message .= 'Переміщено з ' . $fromList . PHP_EOL;
            $message .= 'Переміщено в ' . $toList . PHP_EOL;
            
            $memberCreator = $action['memberCreator'];
            $userName = $memberCreator['fullName']; ; 
            
            $message .= 'Переміщено користувачем: ' . $userName . PHP_EOL;

            $this->telegramController->sendMessage(env('TELEGRAM_GROUP_ID'), $message);
        }
        

        return response('OK', 200);
    }

    private function registerWebhook(){

        $response = Http::asForm()->post('https://api.trello.com/1/webhooks', [
            'key' => env('TRELLO_API_KEY'),
            'token' => env('TRELLO_TOKEN'),
            'description' => 'Trello Card Movement Webhook',
            'callbackURL' => 'https://trello.atpslay.org.ua/trello/webhook',
            'idModel' => '6788edb0aa413468877015bf'
        ]);

        Log::info('Webhook registration response:', $response->json());

        return response()->json($response->json());
    }

    private function connectGet($url){

        $response = Http::get($url, [
            'key' => env('TRELLO_API_KEY'),
            'token' => env('TRELLO_TOKEN')
        ]);


        return response()->json($response->json());
    }

    private function connectPost($url, $params){

        $params = array_merge($params, [
             'key' => env('TRELLO_API_KEY'),
             'token' => env('TRELLO_TOKEN')
        ]);

        $response = Http::post($url, $params);

        return response()->json($response->json());
    }

    public function getBoards(){

        $response = $this->connectGet("https://api.trello.com/1/members/me/boards");

        return $response;
    }

    public function getLists($boardId){

        $response = $this->connectGet("https://api.trello.com/1/boards/{$boardId}/lists");

        return $response;
    }


    private function createList($boardId,$listParams){

        $response = $this->connectPost("https://api.trello.com/1/boards/{$boardId}/lists", $listParams);

        return $response;
    }



    public function createCard($cardParams){

        // $boardId = $request->input('board_id'); 
        // $listId = $request->input('list_id'); 
        // $cardName = $request->input('card_name'); 
        // $cardDescription = $request->input('card_description'); 

        // $cardParams = [
        //     'board_id' => $boardId,
        //     'idList' => $listId,
        //     'name' => $cardName,
        //     'desc' => $cardDescription
        // ];


        $response = $this->connectPost('https://api.trello.com/1/card', $cardParams);

        return $response;
    }


    /**
    * Обробка Oauth Trello
    */
    public function handleTrelloCallback(Request $request, Telegram $telegramModel){
        $accessToken = $request->query('token');
        $chatId = $request->query('chat_id');
    
        if ($accessToken != null && $chatId != null) {
            if(!$telegramModel->getToken($chatId)){
                $telegramModel->setAccessToken($chatId, $accessToken);
            }
     
            $message = 'Авторизація успішна';

            $this->telegramController->sendMessage($chatId, $message);

            return response()->make('
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Trello Callback</title>
                </head>
                <body>
                    <h1>Trello Callback</h1>
                    <p>Авторизація успішна.<br>Ви можете закрити цю сторінку і повернутись до бота</p>
                </body>
                </html>
            ');
        } else {
            return response()->make('
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Trello Callback</title>
                </head>
                <body>
                    <h1>Trello Callback</h1>
                    <script>

                        let fragment = window.location.hash.substring(1); 

                        let token = null;
                        const params = fragment.split("&");
                        params.forEach(function(param) {
                            const [key, value] = param.split("=");
                            if (key === "token") {
                                token = value;
                            }
                        });

                        if (token) {
                            let url = window.location.href.split("#")[0];  
                            let newUrl = url + "&token=" + encodeURIComponent(token);  
                            window.location.replace(newUrl); 
                        }
                    </script>
                      <p>Авторизація не вдалась.<br>Ви можете закрити цю сторінку і повернутись до бота</p>
                </body>
                </html>
            ');
        }
    }
    




  
}
