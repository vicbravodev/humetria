<?php

declare(strict_types=1);

use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ProgramState;
use App\Modules\Interventions\Enums\RowState;
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
    'one planned' => [[ActionStatus::Planned], RowState::InProgress],
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
