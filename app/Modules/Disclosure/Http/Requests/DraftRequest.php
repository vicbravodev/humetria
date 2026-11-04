<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What HR writes in a draft: the sections, each with its key, its text and its items. The sizes of the whole are the
 * action's to judge; this only checks the shape.
 */
final class DraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-disclosure') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['required', 'array'],
            'sections.*.key' => ['required', 'string', 'max:40'],
            'sections.*.text' => ['nullable', 'string', 'max:'.config()->integer('disclosure.text_max')],
            'sections.*.items' => ['nullable', 'array'],
            'sections.*.items.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array{key: string, text: string, items: list<string>}>
     */
    public function sections(): array
    {
        $sections = [];

        foreach ($this->array('sections') as $section) {
            if (! is_array($section)) {
                continue;
            }

            $items = is_array($section['items'] ?? null) ? array_values(array_filter($section['items'], is_string(...))) : [];
            $sections[] = ['key' => is_string($section['key'] ?? null) ? $section['key'] : '', 'text' => is_string($section['text'] ?? null) ? $section['text'] : '', 'items' => $items];
        }

        return $sections;
    }
}
