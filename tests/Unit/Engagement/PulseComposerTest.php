<?php

declare(strict_types=1);

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Bank\PulseComposer;
use App\Modules\Engagement\Support\Bank\Questionnaire;

mutates(PulseComposer::class, PulseMode::class);

const PULSE_FIXED = ['eng.pride@1', 'eng.motivation@1', 'eng.connection@1', 'eng.recommend@1', 'loop.action@1'];

/** @return list<string> the rotating refs, those after the fixed ones */
function pulseRotating(array $refs): array
{
    return array_values(array_diff($refs, PULSE_FIXED));
}

/** @return list<string> */
function pulseDrivers(array $refs): array
{
    return array_values(array_unique(array_map(fn (string $ref): string => ItemBank::find(explode('@', $ref)[0])->driver?->value ?? 'none', $refs)));
}

it('names the three modes', function (): void {
    expect(array_map(fn (PulseMode $mode): string => $mode->label(), PulseMode::cases()))->toBe(['Seguimiento', 'Recorrido', 'Jefe'])
        ->and(PulseMode::FollowUp->value)->toBe('follow_up')
        ->and(PulseMode::Tour->value)->toBe('tour')
        ->and(PulseMode::Manager->value)->toBe('manager');
});

it('starts with the fixed questions, never goes over eleven and never asks an open one', function (PulseMode $mode): void {
    foreach ([0, 1, 2, 3] as $position) {
        $refs = PulseComposer::compose($mode, [Driver::Clarity, Driver::Communication], $position, true);

        expect(array_slice($refs, 0, 5))->toBe(PULSE_FIXED)
            ->and(count($refs))->toBeLessThanOrEqual(PulseComposer::MAX)
            ->and(PulseComposer::MAX)->toBe(11)
            ->and(array_filter($refs, fn (string $ref): bool => ItemBank::find(explode('@', $ref)[0])->scale === ItemScale::Open))->toBe([]);
    }
})->with([PulseMode::FollowUp, PulseMode::Tour, PulseMode::Manager]);

it('leaves the loop question out of the first wave of a company', function (): void {
    $refs = PulseComposer::compose(PulseMode::Tour, [], 0, false);

    expect(array_slice($refs, 0, 4))->toBe(['eng.pride@1', 'eng.motivation@1', 'eng.connection@1', 'eng.recommend@1'])
        ->and($refs)->not->toContain('loop.action@1')
        ->and(count($refs))->toBe(9);
});

it('follows up the two first drivers with their questions in the order of the bank', function (): void {
    $refs = PulseComposer::compose(PulseMode::FollowUp, [Driver::Recognition, Driver::Growth, Driver::Team], 0, true);

    expect(pulseRotating($refs))->toBe(['recognition.specific@1', 'recognition.criteria@1', 'growth.apply@1', 'growth.path@1']);
});

it('drops the candidate question when the two drivers hold more than six', function (): void {
    $refs = PulseComposer::compose(PulseMode::FollowUp, [Driver::Clarity, Driver::Communication], 0, true);

    expect(pulseRotating($refs))->toHaveCount(6)
        ->and($refs)->not->toContain('communication.change@1')
        ->and($refs)->toContain('communication.informed@1', 'clarity.goals@1');
});

it('drops the last candidate first and keeps the other one when only one has to go', function (): void {
    $refs = PulseComposer::compose(PulseMode::FollowUp, [Driver::Communication, Driver::Reward], 0, true);

    expect(pulseRotating($refs))->toHaveCount(6)
        ->and($refs)->not->toContain('reward.benefits@1')
        ->and($refs)->toContain('communication.change@1');
});

it('follows up a single prioritized driver with only its questions', function (): void {
    $refs = PulseComposer::compose(PulseMode::FollowUp, [Driver::Voice], 0, true);

    expect(pulseRotating($refs))->toBe(['voice.disagree@1', 'voice.ideas@1', 'voice.fair_rules@1']);
});

it('refuses a follow-up without prioritized drivers', function (): void {
    PulseComposer::compose(PulseMode::FollowUp, [], 0, true);
})->throws(InvalidArgumentException::class);

it('tours five anchors, the other half the next time, and covers the ten drivers in two pulses', function (): void {
    $first = pulseRotating(PulseComposer::compose(PulseMode::Tour, [], 0, true));
    $second = pulseRotating(PulseComposer::compose(PulseMode::Tour, [], 1, true));

    expect($first)->toHaveCount(5)->and($second)->toHaveCount(5)
        ->and(array_unique([...pulseDrivers($first), ...pulseDrivers($second)]))->toHaveCount(10)
        ->and($first)->toBe(['clarity.contribution@1', 'leadership.reasons@1', 'communication.informed@1', 'recognition.specific@1', 'growth.apply@1'])
        ->and(PulseComposer::compose(PulseMode::Tour, [], 2, true))->toBe(PulseComposer::compose(PulseMode::Tour, [], 0, true));
});

it('asks the manager two questions and four anchors without repeating', function (): void {
    $refs = PulseComposer::compose(PulseMode::Manager, [Driver::Clarity, Driver::Reward], 0, true);

    expect(pulseRotating($refs))->toBe(['manager.guidance@1', 'manager.priorities@1', 'clarity.contribution@1', 'reward.pay@1', 'leadership.reasons@1', 'communication.informed@1']);
});

it('completes the manager anchors with the tour when no driver was prioritized, skipping repeats', function (): void {
    $refs = PulseComposer::compose(PulseMode::Manager, [Driver::Leadership], 1, true);

    expect(pulseRotating($refs))->toBe(['manager.guidance@1', 'manager.priorities@1', 'leadership.reasons@1', 'autonomy.organize@1', 'resources.tools@1', 'team.coordination@1'])
        ->and(count(array_unique($refs)))->toBe(count($refs));
});

it('uses at most the first two prioritized drivers for the manager anchors', function (): void {
    $refs = PulseComposer::compose(PulseMode::Manager, [Driver::Clarity, Driver::Growth, Driver::Team], 0, true);

    expect($refs)->toContain('clarity.contribution@1', 'growth.apply@1')->not->toContain('team.coordination@1');
});

it('keeps a manager pulse at nine questions or fewer for the person with no manager', function (): void {
    $refs = PulseComposer::compose(PulseMode::Manager, [], 0, true);
    $shown = Questionnaire::presentedTo($refs, false);

    expect(count($shown))->toBeLessThanOrEqual(9)
        ->and(array_filter($shown, fn (BankItem $item): bool => $item->block === ItemBlock::Manager))->toBe([]);
});

it('does not mix the manager questions into the other modes', function (): void {
    foreach ([PulseMode::FollowUp, PulseMode::Tour] as $mode) {
        $refs = PulseComposer::compose($mode, [Driver::Clarity], 0, true);

        expect(array_filter($refs, fn (string $ref): bool => str_starts_with($ref, 'manager.')))->toBe([]);
    }
});
