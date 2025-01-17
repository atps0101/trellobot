<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\TrelloController;
use Illuminate\Support\Facades\DB;

use App\Models\Telegram;

class TelegramController extends Controller
{
    private $telegramToken;
    private $webhookUrl;
    protected $trelloController;
    public function __construct(){
        try {
            $this->telegramToken = env('TELEGRAM_BOT_TOKEN'); 
            $this->webhookUrl = config('app.url')."/telegram/webhook"; 
        } catch (\Exception $e) {
            Log::error('Error in TrelloController constructor', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
    * Встановлення нового Webhook
    **/
    public function setWebhook()
    {
        $response = Http::post("https://api.telegram.org/bot{$this->telegramToken}/setWebhook", [
            'url' => $this->webhookUrl,
        ]);


        Log::info('New Webhook set:', $response->json());

        return $response;

    }

    public function handleWebhook(Request $request, Telegram $telegramModel)
    {
        $update = $request->all();
    
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'];
            $firstName = $message['from']['first_name'] ?? '';
            $lastName = $message['from']['last_name'] ?? '';
    
            $user = $telegramModel->getUserByChatId($chatId);
            $trello_access_token = $user ? $user->trello_access_token : null;
    
            if ($text === '/start') {
                return $this->handleStartCommand($telegramModel, $user, $chatId, $firstName, $lastName, $message['from']['username'], $trello_access_token);
            }
    
            if ($text === '/trelloauth') {
                return $this->sendAuthLink($chatId);
            }
    
            if ($text === '/getBoardLink' && $trello_access_token) {
                return $this->sendBoardLink($chatId, $trello_access_token);
            }
    
            $this->sendMessage($chatId, 'Невідома команда');
            $this->setBotCommands();
        }
    
        return response('OK', 200);
    }
    
    private function handleStartCommand($telegramModel, $user, $chatId, $firstName, $lastName, $username, $trello_access_token)
    {
        if ($user) {
            $this->sendMessage($chatId, 'Привіт, ' . $user->first_name . '!');
            
            if (!$trello_access_token) {
                return $this->sendAuthLink($chatId);
            }

            
            $this->sendBoardLink($chatId, $trello_access_token);
            $this->sendUserBoards($chatId, $trello_access_token);

        } else {

            $userData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'username' => $username,
                'chat_id' => $chatId,
                'pm' => 1,  
            ];
    
            $telegramModel->addUser($userData);
            $this->sendMessage($chatId, 'Привіт, ' . $firstName . '!');
            $this->sendAuthLink($chatId);
        }

        return response('OK', 200);
    }

    private function sendUserBoards($chatId, $accessToken){
        $boards = TrelloController::getUserBoards($accessToken);
    
        if (empty($boards)) {
            $this->sendMessage($chatId, "У вас немає дошок");
            return;
        }
    
        $message = 'Ваші дошки:' . PHP_EOL; 
    
        foreach($boards as $board){
            $message .= 'ID: ' . $board['id'] . PHP_EOL; 
            $message .= 'Назва: ' . $board['name'] . PHP_EOL . PHP_EOL; 
        }
    
        $this->sendMessage($chatId, $message); 
    }


    private function sendBoardLink($chatId, $accessToken){

        $boardLink = TrelloController::getBoardLink(env('TRELLO_BOARD_ID'), $accessToken);

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Лінк на дошку', 
                        'url' => $boardLink
                    ]
                ]
            ]
        ];


        $this->sendMessageWithKeyboard($chatId, 'Натисніть кнопку', $keyboard);
    }
    
    private function sendAuthLink($chatId){

        $appUrl = config('app.url');
        $trelloAppName = env('TRELLO_APP_NAME');
        $apiKey = env('TRELLO_API_KEY'); 
        $callbackUrl = $appUrl."/trello/callback?chat_id=".$chatId; 
        $authUrl = "https://trello.com/1/authorize?expiration=never&name=".$trelloAppName."&scope=read,write&response_type=code&key=".$apiKey."&return_url=".$callbackUrl;

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Авторизуйтесь в Trello', 
                        'url' => $authUrl
                    ]
                ]
            ]
        ];


        $this->sendMessageWithKeyboard($chatId, 'Натисніть кнопку для авторизації в Trello.', $keyboard);
    }

    private function sendMessageWithKeyboard($chatId, $message, $keyboard){
        $token = env('TELEGRAM_BOT_TOKEN'); 

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode($keyboard), 
        ];

        Http::post($url, $params);
    }

    
    /**
     * @param int $chatId
     * @param string $message
     */
    public function sendMessage($chatId, $message)
    {
        $response = Http::post("https://api.telegram.org/bot{$this->telegramToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

    }

    private function setBotCommands() {
        $response = Http::post("https://api.telegram.org/bot{$this->telegramToken}/setMyCommands", [
            'commands' => json_encode([
                [
                    'command' => '/trelloauth',
                    'description' => 'Авторизація в Trello',
                ],
                [
                    'command2' => '/getBoardLink',
                    'description' => 'Лінк на дошку',
                ],
            ])
        ]);

    }



    
}
