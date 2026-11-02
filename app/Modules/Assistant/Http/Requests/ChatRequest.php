<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A message to Humi: the text, and which conversation it continues and which page the person has open. Nothing else is
 * read from the browser: the history, system and assistant messages stay on the server.
 */
final class ChatRequest extends FormRequest
{
    private const int MAX_LENGTH = 2000;

    public function authorize(): bool
    {
        return Gate::allows('use-assistant');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'conversationId' => ['nullable', 'uuid'],
            'pageContext' => ['nullable', 'array'],
        ];
    }

    public function messageText(): string
    {
        return $this->string('message')->toString();
    }

    public function conversationId(): ?string
    {
        return $this->filled('conversationId') ? $this->string('conversationId')->toString() : null;
    }
}
