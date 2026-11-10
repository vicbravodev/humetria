<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Bank;

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\PulseMode;
use InvalidArgumentException;

/**
 * The questions of a pulse: five fixed ones and up to six that rotate, in exactly one of three modes. Pure: it only
 * reads the bank.
 */
final class PulseComposer
{
    public const int MAX = 11;

    /** The most questions a mode adds to the fixed ones. */
    private const int ROTATING = 6;

    /** The drivers a tour looks at, half each time: two tours in a row cover the ten. */
    public const array TOUR = [
        [Driver::Clarity, Driver::Leadership, Driver::Communication, Driver::Recognition, Driver::Growth],
        [Driver::Autonomy, Driver::Resources, Driver::Team, Driver::Voice, Driver::Reward],
    ];

    /**
     * @param  list<Driver>  $prioritized  the drivers to act on first after the last climate, in order
     * @param  int  $tourPosition  the tours the company already had
     * @param  bool  $hasPreviousWave  whether the company closed a survey before
     * @return list<string> refs (`key@wording`), the fixed ones first
     *
     * @throws InvalidArgumentException a follow-up with nothing to follow
     */
    public static function compose(PulseMode $mode, array $prioritized, int $tourPosition, bool $hasPreviousWave): array
    {
        $tour = self::TOUR[$tourPosition % 2];
        $rotating = match ($mode) {
            PulseMode::FollowUp => self::followUp($prioritized),
            PulseMode::Tour => array_map(fn (Driver $driver): BankItem => ItemBank::anchor($driver), $tour),
            PulseMode::Manager => self::manager($prioritized, $tour),
        };

        return array_map(fn (BankItem $item): string => $item->ref(), [...self::fixed($hasPreviousWave), ...$rotating]);
    }

    /** @return list<BankItem> */
    private static function fixed(bool $hasPreviousWave): array
    {
        $keys = [...ItemBank::INDEX, ItemBank::RECOMMEND, ...($hasPreviousWave ? [ItemBank::LOOP] : [])];

        return array_map(ItemBank::find(...), $keys);
    }

    /**
     * Every question of the first two drivers; over six, the candidates go first, the last one first, then the last.
     *
     * @param  list<Driver>  $prioritized
     * @return list<BankItem>
     */
    private static function followUp(array $prioritized): array
    {
        if ($prioritized === []) {
            throw new InvalidArgumentException('A follow-up needs at least one prioritized driver.');
        }

        $drivers = array_slice($prioritized, 0, 2);
        $items = array_values(array_filter(ItemBank::all(), fn (BankItem $item): bool => $item->driver !== null && in_array($item->driver, $drivers, true) && $item->block === ItemBlock::Company));

        foreach (array_reverse(array_keys(array_filter($items, fn (BankItem $item): bool => $item->candidate))) as $position) {
            if (count($items) <= self::ROTATING) {
                break;
            }

            unset($items[$position]);
        }

        return array_slice(array_values($items), 0, self::ROTATING);
    }

    /**
     * The two questions about the manager and four anchors: those of the prioritized drivers, then the tour in turn.
     *
     * @param  list<Driver>  $prioritized
     * @param  list<Driver>  $tour
     * @return list<BankItem>
     */
    private static function manager(array $prioritized, array $tour): array
    {
        $anchors = [];

        foreach ([...array_slice($prioritized, 0, 2), ...$tour] as $driver) {
            $anchors[$driver->value] = ItemBank::anchor($driver);
        }

        return [ItemBank::find('manager.guidance'), ItemBank::find('manager.priorities'), ...array_slice(array_values($anchors), 0, 4)];
    }
}
