<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantMessage extends Model
{
    protected $table = 'assistant_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_name',
        'tool_input',
        'tool_output',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'tool_input' => 'array',
        'tool_output' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }
}
