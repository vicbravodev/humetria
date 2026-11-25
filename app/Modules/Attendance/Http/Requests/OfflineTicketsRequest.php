<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A phone asks for a new batch of tickets with a valid one of the person (and may say how long its queue of marks is, only
 * to see queues that do not deliver). `authorize()` is true on purpose: the credential is the ticket, which is inert
 * without the signature of a mark, as the signature is the credential of `MarkRequest`.
 */
final class OfflineTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticket' => ['required', 'string', 'max:256', 'not_regex:/\|/'],
            'queue' => ['nullable', 'array'],
            'queue.count' => ['integer', 'min:0'],
            'queue.oldest_age_minutes' => ['integer', 'min:0'],
        ];
    }

    public function ticket(): string
    {
        return $this->string('ticket')->toString();
    }

    /**
     * The queue the phone reported, when it did: how many marks wait and the age in minutes of the oldest.
     *
     * @return array{count: int, oldest_age_minutes: int}|null
     */
    public function queue(): ?array
    {
        return $this->has('queue.count') && $this->has('queue.oldest_age_minutes')
            ? ['count' => $this->integer('queue.count'), 'oldest_age_minutes' => $this->integer('queue.oldest_age_minutes')]
            : null;
    }
}
