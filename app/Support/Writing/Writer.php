<?php

declare(strict_types=1);

namespace App\Support\Writing;

use Illuminate\Validation\ValidationException;

/**
 * The engine of the documents written with questions (decisión A3), whatever the module: what the answers of a template start
 * from, which answers it takes, and the text they make with the paragraphs RH rewrote. Which kinds are written with which
 * template is each module's own (NOM-037's Templates, NOM-035's Templates).
 */
final readonly class Writer
{
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

    /**
     * The paragraphs RH rewrote that still stand: a question that gets another answer drops the one of its section.
     *
     * @param  array<string, string>  $edits
     * @param  array<string, string|list<string>|null>  $before
     * @param  array<string, string|list<string>|null>  $after
     * @return array<string, string>
     */
    public function standing(WrittenTemplate $template, array $edits, array $before, array $after): array
    {
        foreach ($template->questions() as $question) {
            if (array_any($question->fields, fn (Field $field): bool => ($before[$field->key] ?? null) !== ($after[$field->key] ?? null))) {
                unset($edits[$question->key]);
            }
        }

        return $edits;
    }

    /**
     * The paragraphs RH rewrote with one more: an empty text puts back the one the answers make.
     *
     * @param  array<string, string>  $edits
     * @param  array{section: string, text: string}  $edit
     * @return array<string, string>
     *
     * @throws ValidationException when the document has no such section.
     */
    public function rewrite(WrittenTemplate $template, array $edits, array $edit): array
    {
        $keys = array_map(fn (Section $section): string => $section->key, $template->sections(new Answers([])));

        if (! in_array($edit['section'], $keys, true)) {
            throw ValidationException::withMessages(['section' => 'El documento no tiene esa sección.']);
        }

        $text = trim(str_replace("\r\n", "\n", $edit['text']));
        unset($edits[$edit['section']]);

        return $text === '' ? $edits : [...$edits, $edit['section'] => $text];
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
