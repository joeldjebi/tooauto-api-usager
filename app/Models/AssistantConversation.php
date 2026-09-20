<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantConversation extends Model
{
    protected $table = 'assistant_conversations';

    protected $fillable = [
        'user_id',
        'user_type',
        'title',
    ];

    public function messages()
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id');
    }

    public function lastAssistantMessage()
    {
        return $this->hasOne(AssistantMessage::class, 'conversation_id')
            ->where('role', 'assistant')
            ->latestOfMany();
    }
}
