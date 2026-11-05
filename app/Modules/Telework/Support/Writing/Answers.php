<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

/** The answers of a draft by field, read the way a template writes its text. */
final readonly class Answers
{
    /**
     * @param  array<string, string|list<string>|null>  $values
     */
    public function __construct(private array $values) {}

    public function one(string $field): ?string
    {
        $value = $this->values[$field] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return list<string> */
    public function many(string $field): array
    {
        $value = $this->values[$field] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * The phrase of the chosen option, or null while none is.
     *
     * @param  array<string, string>  $phrases
     */
    public function phrase(string $field, array $phrases): ?string
    {
        return $phrases[$this->one($field) ?? ''] ?? null;
    }

    /**
     * The phrase of each chosen option, in the order of `$phrases`, joined the way Spanish lists are ("a, b y c"), or null
     * when none is chosen.
     *
     * @param  array<string, string>  $phrases
     */
    public function list(string $field, array $phrases, string $last = 'y'): ?string
    {
        $chosen = array_values(array_intersect_key($phrases, array_flip($this->many($field))));

        return match (count($chosen)) {
            0 => null,
            1 => $chosen[0],
            default => implode(', ', array_slice($chosen, 0, -1))." {$last} ".$chosen[count($chosen) - 1],
        };
    }
}
