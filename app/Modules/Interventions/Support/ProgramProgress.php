<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ProgramState;
use App\Modules\Interventions\Enums\RowState;

/**
 * The one place where the states of the Programa are derived: whether an action is overdue, where a row stands, the meter of
 * actions done and where the Programa stands. The page, the report and the reminders all ask here. A cancelled action counts
 * for none of it (criterio C-35: it stays in the record, not in the progress).
 */
final class ProgramProgress
{
    /** Due before `$today` (Y-m-d) and still planned or running; the due day itself is not overdue. */
    public static function isOverdue(ActionStatus $status, string $dueOn, string $today): bool
    {
        return in_array($status, [ActionStatus::Planned, ActionStatus::InProgress], true) && $dueOn < $today;
    }

    /**
     * @param  list<ActionStatus>  $statuses  the actions of a row
     */
    public static function rowState(array $statuses): RowState
    {
        $counted = self::counted($statuses);

        return match (true) {
            $counted === [] => RowState::NoActions,
            array_all($counted, fn (ActionStatus $status): bool => $status === ActionStatus::Done) => RowState::Done,
            default => RowState::InProgress,
        };
    }

    /**
     * @param  list<ActionStatus>  $statuses  the actions of a Programa
     * @return array{done: int, total: int}
     */
    public static function meter(array $statuses): array
    {
        $counted = self::counted($statuses);

        return ['done' => count(array_filter($counted, fn (ActionStatus $status): bool => $status === ActionStatus::Done)), 'total' => count($counted)];
    }

    /**
     * Done when every row is done and no follow-up evaluation (num. 8.4 e) waits; with nothing to do yet, no actions.
     *
     * @param  list<array{state: RowState, followUpPending: bool}>  $rows
     */
    public static function programState(array $rows, bool $anyOverdue): ProgramState
    {
        return match (true) {
            array_all($rows, fn (array $row): bool => $row['state'] === RowState::NoActions) => ProgramState::NoActions,
            $anyOverdue => ProgramState::Overdue,
            array_all($rows, fn (array $row): bool => $row['state'] === RowState::Done && ! $row['followUpPending']) => ProgramState::Done,
            default => ProgramState::InProgress,
        };
    }

    /**
     * @param  list<ActionStatus>  $statuses
     * @return list<ActionStatus>
     */
    private static function counted(array $statuses): array
    {
        return array_values(array_filter($statuses, fn (ActionStatus $status): bool => $status !== ActionStatus::Cancelled));
    }
}
