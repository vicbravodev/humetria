<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\DocumentTemplates;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Saves what HR wrote in a draft (criterio C-50). Only the words are theirs: the keys, headings and list styles come from the
 * template of the kind, so the sections come back in its order, a principle may be left out and the ones the guide requires
 * may not. Saving checks only the size (the policy is published only when it is complete). Plain text, trimmed, with no
 * empty item.
 */
final readonly class SaveDraft
{
    public function __construct(private DocumentTemplates $templates) {}

    /**
     * @param  list<array{key: string, text: string, items: list<string>}>  $sections
     *
     * @throws ValidationException
     * @throws ModelNotFoundException when the draft does not exist, is published or is another company's.
     */
    public function handle(string $documentId, array $sections): Document
    {
        return DB::transaction(function () use ($documentId, $sections): Document {
            $draft = Document::query()->whereKey($documentId)->where('status', DocumentStatus::Draft)->lockForUpdate()->firstOrFail();
            $draft->update(['content' => $this->content($draft, $sections)]);
            Log::info('disclosure.draft_saved', ['document_id' => $draft->id, 'kind' => $draft->kind->value]);

            return $draft;
        });
    }

    /**
     * @param  list<array{key: string, text: string, items: list<string>}>  $sections
     * @return list<array{key: string, heading: string, text: string, items: list<string>, list_style: string|null}>
     */
    private function content(Document $draft, array $sections): array
    {
        $template = $this->templates->sections($draft->kind);
        $sent = [];

        foreach ($sections as $section) {
            if (isset($sent[$section['key']])) {
                throw ValidationException::withMessages(['sections' => 'Una sección viene repetida.']);
            }
            $sent[$section['key']] = $section;
        }

        if (array_diff(array_keys($sent), array_map(fn ($section): string => $section->key, $template)) !== []) {
            throw ValidationException::withMessages(['sections' => 'Hay una sección que la plantilla no tiene.']);
        }

        $content = [];
        $characters = 0;

        foreach ($template as $part) {
            $section = $sent[$part->key] ?? null;

            if ($section === null) {
                if (! $part->removable) {
                    throw ValidationException::withMessages(['sections' => "La sección «{$part->heading}» no se puede quitar."]);
                }

                continue;
            }

            $text = trim(str_replace("\r\n", "\n", $section['text']));
            $items = array_values(array_filter(array_map(trim(...), $section['items']), fn (string $item): bool => $item !== ''));

            if (count($items) > config()->integer('disclosure.items_max')) {
                throw ValidationException::withMessages(['sections' => "«{$part->heading}» tiene demasiados elementos."]);
            }

            $characters += mb_strlen($text) + array_sum(array_map(mb_strlen(...), $items));
            $content[] = ['key' => $part->key, 'heading' => $part->heading, 'text' => $text, 'items' => $items, 'list_style' => $part->listStyle];
        }

        if ($characters > config()->integer('disclosure.text_max')) {
            throw ValidationException::withMessages(['sections' => 'El texto es demasiado largo.']);
        }

        return $content;
    }
}
