<?php

declare(strict_types=1);

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ProgramState;
use App\Modules\Interventions\Enums\RowState;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\ProgramProgress;

mutates(ProgramProgress::class);

it('is overdue the day after the due date and not on it, while it is not finished', function (ActionStatus $status, string $dueOn, bool $overdue): void {
    expect(ProgramProgress::isOverdue($status, $dueOn, '2026-11-20'))->toBe($overdue);
})->with([
    'planned, due yesterday' => [ActionStatus::Planned, '2026-11-19', true],
    'in progress, due yesterday' => [ActionStatus::InProgress, '2026-11-19', true],
    'planned, due today' => [ActionStatus::Planned, '2026-11-20', false],
    'planned, due tomorrow' => [ActionStatus::Planned, '2026-11-21', false],
    'done, due long ago' => [ActionStatus::Done, '2026-01-01', false],
    'cancelled, due long ago' => [ActionStatus::Cancelled, '2026-01-01', false],
]);

it('tells where a row stands from the states of its actions, leaving the cancelled ones out', function (array $statuses, RowState $state): void {
    expect(ProgramProgress::rowState($statuses))->toBe($state);
})->with([
    'no actions' => [[], RowState::NoActions],
    'only cancelled ones' => [[ActionStatus::Cancelled, ActionStatus::Cancelled], RowState::NoActions],
    'one planned: not started' => [[ActionStatus::Planned], RowState::NotStarted],
    'only planned ones and a cancelled one: not started' => [[ActionStatus::Planned, ActionStatus::Planned, ActionStatus::Cancelled], RowState::NotStarted],
    'one running' => [[ActionStatus::InProgress], RowState::InProgress],
    'one done, one planned' => [[ActionStatus::Done, ActionStatus::Planned], RowState::InProgress],
    'all done' => [[ActionStatus::Done, ActionStatus::Done], RowState::Done],
    'done and cancelled' => [[ActionStatus::Done, ActionStatus::Cancelled], RowState::Done],
]);

it('counts what is done of what is not cancelled', function (array $statuses, int $done, int $total): void {
    expect(ProgramProgress::meter($statuses))->toBe(['done' => $done, 'total' => $total]);
})->with([
    'nothing' => [[], 0, 0],
    'cancelled ones do not count' => [[ActionStatus::Done, ActionStatus::Cancelled, ActionStatus::Planned, ActionStatus::InProgress], 1, 3],
    'all done' => [[ActionStatus::Done, ActionStatus::Done], 2, 2],
]);

it('tells where the Programa stands', function (array $rows, bool $overdue, ProgramState $state): void {
    expect(ProgramProgress::programState($rows, $overdue))->toBe($state);
})->with([
    'no rows' => [[], false, ProgramState::NoActions],
    'rows without actions' => [[['state' => RowState::NoActions, 'followUpPending' => false]], false, ProgramState::NoActions],
    'one row running' => [[['state' => RowState::InProgress, 'followUpPending' => false]], false, ProgramState::InProgress],
    'a row running and one without actions' => [[['state' => RowState::InProgress, 'followUpPending' => false], ['state' => RowState::NoActions, 'followUpPending' => false]], false, ProgramState::InProgress],
    'overdue wins over running' => [[['state' => RowState::InProgress, 'followUpPending' => false]], true, ProgramState::Overdue],
    'every row done' => [[['state' => RowState::Done, 'followUpPending' => false], ['state' => RowState::Done, 'followUpPending' => false]], false, ProgramState::Done],
    'rows done but a follow-up evaluation waits' => [[['state' => RowState::Done, 'followUpPending' => true]], false, ProgramState::InProgress],
    'a row done and one without actions' => [[['state' => RowState::Done, 'followUpPending' => false], ['state' => RowState::NoActions, 'followUpPending' => false]], false, ProgramState::InProgress],
]);

it('says a row whose actions are all done still waits for its follow-up evaluation (num. 8.4 e)', function (array $statuses, bool $pending, RowState $state): void {
    expect(ProgramProgress::rowState($statuses, $pending))->toBe($state);
})->with([
    'all done, follow-up pending' => [[ActionStatus::Done, ActionStatus::Cancelled], true, RowState::AwaitingFollowUp],
    'all done, no follow-up pending' => [[ActionStatus::Done], false, RowState::Done],
    'running, follow-up pending' => [[ActionStatus::Done, ActionStatus::InProgress], true, RowState::InProgress],
    'nothing yet, follow-up pending' => [[], true, RowState::NoActions],
]);

it('names the states of a row as the panel reads them', function (): void {
    expect(array_map(fn (RowState $state): string => $state->label(), RowState::cases()))
        ->toBe(['Sin acciones', 'Sin empezar', 'En curso', 'Hecha, falta la evaluación posterior', 'Terminada']);
});

it('does not call a Programa done while a row waits for its follow-up evaluation', function (): void {
    expect(ProgramProgress::programState([['state' => RowState::AwaitingFollowUp, 'followUpPending' => true]], false))->toBe(ProgramState::InProgress)
        ->and(ProgramProgress::programState([['state' => RowState::NotStarted, 'followUpPending' => false]], false))->toBe(ProgramState::InProgress);
});

/**
 * A row of a Programa, without touching the database.
 */
function progressRow(string $id, ?string $followUpDue = null, ?string $followUpDone = null): ProgramItem
{
    // Raw attributes: a unit test has no connection to format dates with.
    return (new ProgramItem)->setRawAttributes(['id' => $id, 'follow_up_due_on' => $followUpDue, 'follow_up_done_on' => $followUpDone]);
}

function progressAction(string $itemId, ActionStatus $status, string $dueOn = '2026-12-01'): ProgramAction
{
    return (new ProgramAction)->setRawAttributes(['item_id' => $itemId, 'status' => $status->value, 'due_on' => $dueOn]);
}

it('counts the rows that have an action not cancelled: the coverage of the Programa', function (): void {
    $rows = [progressRow('a'), progressRow('b'), progressRow('c')];
    $actions = [progressAction('a', ActionStatus::Planned), progressAction('b', ActionStatus::Cancelled)];

    expect(ProgramProgress::standing($rows, $actions, '2026-11-20')['coverage'])->toBe(['covered' => 1, 'total' => 3]);
});

it('never reads 100 % while a row has no action or a follow-up evaluation waits', function (array $rows, array $actions, int $percent): void {
    expect(ProgramProgress::standing($rows, $actions, '2026-11-20')['percent'])->toBe($percent);
})->with([
    'nothing' => [[], [], 0],
    'one action done of one, nine rows without actions' => fn (): array => [
        [progressRow('a'), ...array_map(fn (int $n): ProgramItem => progressRow("r{$n}"), range(1, 9))],
        [progressAction('a', ActionStatus::Done)],
        10,
    ],
    'every action done, a follow-up waits' => fn (): array => [
        [progressRow('a', '2027-01-10'), progressRow('b')],
        [progressAction('a', ActionStatus::Done), progressAction('b', ActionStatus::Done)],
        66,
    ],
    'every action done and the follow-up done' => fn (): array => [
        [progressRow('a', '2027-01-10', '2026-11-19'), progressRow('b')],
        [progressAction('a', ActionStatus::Done), progressAction('b', ActionStatus::Done), progressAction('b', ActionStatus::Cancelled)],
        100,
    ],
    'half the actions done, every row covered' => fn (): array => [
        [progressRow('a')],
        [progressAction('a', ActionStatus::Done), progressAction('a', ActionStatus::InProgress)],
        50,
    ],
    'it rounds down, never up to 100' => fn (): array => [
        [progressRow('a')],
        [...array_fill(0, 199, progressAction('a', ActionStatus::Done)), progressAction('a', ActionStatus::Planned)],
        99,
    ],
]);

it('reads 100 % exactly when the Programa is done', function (): void {
    $standing = ProgramProgress::standing([progressRow('a')], [progressAction('a', ActionStatus::Done)], '2026-11-20');

    expect($standing['percent'])->toBe(100)
        ->and($standing['state'])->toBe(ProgramState::Done)
        ->and($standing['rows']['a']['state'])->toBe(RowState::Done);
});
