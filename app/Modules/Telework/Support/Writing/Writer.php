<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

use App\Modules\Telework\Enums\DocumentKind;
use Illuminate\Validation\ValidationException;

/**
 * The engine of the NOM-037 documents written with questions (decisión A3): which kinds are written that way, what their
 * answers start from, which answers a template takes, and the text they make with the paragraphs RH rewrote.
 */
final readonly class Writer
{
    /** The template a kind is written with, or null for a kind that is only uploaded (the inspection report). */
    public function template(DocumentKind $kind): ?WrittenTemplate
    {
        return match ($kind) {
            DocumentKind::Policy => new PolicyTemplate,
            default => null,
        };
    }

    /**
     * Every field of the template with what the board marks before anyone answers.
     *
     * @return array<string, string|list<string>|null>
     */
    public function defaults(WrittenTemplate $template): array
    {
        $answers = [];

        foreach ($this->fields($template) as $field) {
            $answers[$field->key] = $field->default ?? ($field->multiple ? [] : null);
        }

        return $answers;
    }

    /**
     * The answers with what was sent on top. A field the template does not have, an option it does not offer, a list for a
     * single choice (or the other way round) or a repeated option is refused.
     *
     * @param  array<string, string|list<string>|null>  $current
     * @param  array<array-key, mixed>  $sent
     * @return array<string, string|list<string>|null>
     *
     * @throws ValidationException
     */
    public function merge(WrittenTemplate $template, array $current, array $sent): array
    {
        $fields = [];

        foreach ($this->fields($template) as $field) {
            $fields[$field->key] = $field;
        }

        foreach ($sent as $key => $value) {
            $field = $fields[$key] ?? null;
            $answer = $field === null ? null : $this->fitted($field, $value);

            if ($field === null || $answer === null) {
                throw ValidationException::withMessages(['answers' => 'Alguna respuesta no corresponde a las preguntas.']);
            }

            $current[$field->key] = $answer;
        }

        return $current;
    }

    /**
     * Whether every question is answered: each single choice taken and each list with something chosen.
     *
     * @param  array<string, string|list<string>|null>  $answers
     */
    public function complete(WrittenTemplate $template, array $answers): bool
    {
        return array_all($this->fields($template), fn (Field $field): bool => ($answers[$field->key] ?? null) !== null && $answers[$field->key] !== []);
    }

    /**
     * The sections with the paragraphs RH rewrote in place of the ones the answers make.
     *
     * @param  array<string, string|list<string>|null>  $answers
     * @param  array<string, string>  $edits
     * @return list<array{key: string, heading: string, text: string|null, base: bool, edited: bool}>
     */
    public function sections(WrittenTemplate $template, array $answers, array $edits = []): array
    {
        return array_map(fn (Section $section): array => [
            'key' => $section->key,
            'heading' => $section->heading,
            'text' => $edits[$section->key] ?? $section->text,
            'base' => $section->base,
            'edited' => isset($edits[$section->key]),
        ], $template->sections(new Answers($answers)));
    }

    /** @return list<Field> */
    private function fields(WrittenTemplate $template): array
    {
        return array_merge([], ...array_map(fn (Question $question): array => $question->fields, $template->questions()));
    }

    /**
     * The answer as the field takes it, or null when it does not fit.
     *
     * @return string|list<string>|null
     */
    private function fitted(Field $field, mixed $value): string|array|null
    {
        if (! $field->multiple) {
            return is_string($value) && array_key_exists($value, $field->options) ? $value : null;
        }

        $options = is_array($value) ? array_values(array_filter($value, is_string(...))) : null;

        return $options !== null && count($options) === count($value) && array_is_list($value)
            && array_diff($options, array_keys($field->options)) === [] && count(array_unique($options)) === count($options)
            ? $options : null;
    }
}
