<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The comments are read one open question at a time, those about the company. */
final class CommentsRequest extends FormRequest
{
    public const array ITEMS = ['open.best', 'open.improve'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['item' => ['nullable', 'string', Rule::in(self::ITEMS)]];
    }

    public function item(): string
    {
        return in_array($this->string('item')->toString(), self::ITEMS, true) ? $this->string('item')->toString() : self::ITEMS[0];
    }
}
