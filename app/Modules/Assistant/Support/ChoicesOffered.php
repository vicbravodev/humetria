<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Tools\OfferChoices;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\ToolResult;

/**
 * Whether the step that just ended put buttons in front of the person: the run stops there, because the question is the
 * card's and nothing the model could add would be read before the person answers. A note that is not a card (the model
 * offered too few options) is not that: the model gets its turn to try again.
 */
final class ChoicesOffered
{
    public static function in(PendingStep $step): bool
    {
        $last = $step->steps === [] ? null : $step->steps[array_key_last($step->steps)];

        return $last !== null && array_any($last->toolResults, fn (ToolResult $result): bool => $result->name === OfferChoices::NAME
            && ! $result->failed
            && self::isCard($result->result));
    }

    private static function isCard(mixed $result): bool
    {
        $card = is_string($result) ? json_decode($result, true) : null;

        return is_array($card) && ($card['render'] ?? null) === 'choices';
    }
}
