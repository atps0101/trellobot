<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Telegram extends Model
{
    use HasFactory;

    protected $table = 'telegram_users';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'trello_access_token',
        'chat_id',
        'pm'
    ];


    public function addUser($data)
    {
        $user = self::create($data);

        return $user ? $user : false;
    }

    public function getUserByChatId($chat_id)
    {
        $user = self::where('chat_id', $chat_id)->first();

        return $user ? $user : false;
    }

    public function getToken($chat_id)
    {
        $token = self::where('chat_id', $chat_id)->value('trello_access_token');
        return $token ? $token : false;
    }

    public function setAccessToken($chat_id, $access_token){
        $user = self::updateOrCreate(
            ['chat_id' => $chat_id], 
            ['trello_access_token' => $access_token] 
        );

        return $user ? $user : false;
    }

}
