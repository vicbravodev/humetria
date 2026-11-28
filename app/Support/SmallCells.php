<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rule 10 of the WhatsApp design for a cross of the channel with "answered": if any cell of any channel is under the minimum, the
 * whole table is hidden. With only the cells that remain and the total of answers the page shows elsewhere, the small one could be
 * got by subtraction, and it would point at one person.
 */
final class SmallCells
{
    private const array ORDER = ['mail', 'whatsapp']; // @pest-mutate-ignore: RemoveArrayItem

    /**
     * @param  array<string, array{answered: int, unanswered: int}>  $cells  by channel
     * @return list<array{channel: string, answered: int, unanswered: int}>|null null when the table must be hidden.
     */
    public static function answeredByChannel(array $cells, int $min): ?array
    {
        $rows = [];

        foreach (self::ORDER as $channel) {
            if (isset($cells[$channel])) {
                $rows[] = ['channel' => $channel, 'answered' => $cells[$channel]['answered'], 'unanswered' => $cells[$channel]['unanswered']];
            }
        }

        foreach ($rows as $row) {
            if ($row['answered'] < $min || $row['unanswered'] < $min) {
                return null;
            }
        }

        return $rows;
    }
}
