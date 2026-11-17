<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ProgramState;
use App\Modules\Interventions\Enums\RowState;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;

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
     * @param  bool  $followUpPending  whether its follow-up evaluation (num. 8.4 e) is scheduled and not done
     */
    public static function rowState(array $statuses, bool $followUpPending = false): RowState
    {
        $counted = self::counted($statuses);

        return match (true) {
            $counted === [] => RowState::NoActions,
            array_all($counted, fn (ActionStatus $status): bool => $status === ActionStatus::Done) => $followUpPending ? RowState::AwaitingFollowUp : RowState::Done,
            array_all($counted, fn (ActionStatus $status): bool => $status === ActionStatus::Planned) => RowState::NotStarted,
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
     * Where a whole Programa stands, from its rows and its actions: the state of each row, the meters, how many rows have an
     * action (the coverage), the share of the work done, how many actions are overdue today and where the Programa is. The
     * page, the list, the results card, the report and the reminders read it from here (criterio C-36: the same numbers).
     *
     * @param  list<ProgramItem>  $items
     * @param  array<int, ProgramAction>  $actions  every action of the Programa
     * @return array{state: ProgramState, meter: array{done: int, total: int}, coverage: array{covered: int, total: int}, percent: int, overdue: int, rows: array<string, array{state: RowState, meter: array{done: int, total: int}, followUpPending: bool}>}
     */
    public static function standing(array $items, array $actions, string $today): array
    {
        $rows = [];

        foreach ($items as $item) {
            $statuses = array_values(array_map(fn (ProgramAction $action): ActionStatus => $action->status, array_filter($actions, fn (ProgramAction $action): bool => $action->item_id === $item->id)));
            $pending = $item->follow_up_due_on !== null && $item->follow_up_done_on === null;
            $rows[$item->id] = ['state' => self::rowState($statuses, $pending), 'meter' => self::meter($statuses), 'followUpPending' => $pending];
        }

        $overdue = count(array_filter($actions, fn (ProgramAction $action): bool => self::isOverdue($action->status, $action->due_on->toDateString(), $today)));
        $meter = self::meter(array_values(array_map(fn (ProgramAction $action): ActionStatus => $action->status, $actions)));
        $coverage = ['covered' => count(array_filter($rows, fn (array $row): bool => $row['meter']['total'] > 0)), 'total' => count($rows)];
        // What is still to do counts against the share: every action not done, every row without one and every follow-up
        // evaluation that waits. So it reads 100 % only when the Programa is done; rounded down, never up to it.
        $work = $meter['total'] + $coverage['total'] - $coverage['covered'] + count(array_filter($rows, fn (array $row): bool => $row['followUpPending']));

        return [
            'state' => self::programState(array_values($rows), $overdue > 0),
            'meter' => $meter,
            'coverage' => $coverage,
            'percent' => $work === 0 ? 0 : intdiv($meter['done'] * 100, $work),
            'overdue' => $overdue,
            'rows' => $rows,
        ];
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
