<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Tools\OfferChoices;

/**
 * What can be counted in an answer of Humi to compare two prompts or two models by number: its words, the links of the
 * panel it gives, whether it closes offering something («si quieres, te…») instead of saying the next step, and whether it
 * asked the person with buttons.
 */
final readonly class AnswerMetrics
{
    private const string OFFER = '/si quieres|si te sirve|quieres que/iu';

    public function __construct(public int $words, public int $links, public bool $endsWithOffer, public bool $asksChoices = false) {}

    /**
     * @param  list<string>  $toolNames  the tools the answer called
     */
    public static function of(string $answer, array $toolNames = []): self
    {
        return new self(
            count(preg_split('/\s+/u', trim($answer), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            substr_count($answer, '/admin/'),
            preg_match(self::OFFER, mb_substr($answer, -200)) === 1,
            in_array(OfferChoices::NAME, $toolNames, true),
        );
    }
}
