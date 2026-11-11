<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole survey of one person. Whether it is the complete, coherent set of the questions they were shown is the
 * action's to say; here only its shape.
 */
final class SubmitResponseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array', 'max:60'],
            'answers.*' => ['nullable', 'integer'],
            'comments' => ['nullable', 'array', 'max:10'],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function answers(): array
    {
        return $this->array('answers');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function comments(): array
    {
        // A box left empty reaches us as null (the middleware turns "" into null): no comment.
        return array_filter($this->array('comments'), fn (mixed $comment): bool => $comment !== null);
    }

    public function minutes(): ?int
    {
        $minutes = $this->input('minutes');

        return is_int($minutes) ? $minutes : null;
    }
}
