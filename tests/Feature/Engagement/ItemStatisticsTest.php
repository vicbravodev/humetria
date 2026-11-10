<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\ItemStatistics;
use App\Modules\Engagement\Support\Results\ItemStatisticsReport;
use App\Modules\Tenancy\Models\Tenant;

mutates(ItemStatistics::class, ItemStatisticsReport::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * Responses of the survey, one per set of answers (and minutes).
 *
 * @param  list<array<string, int|null>>  $answers
 * @param  list<int|null>  $minutes
 */
function pilotResponses(Survey $survey, array $answers, array $minutes = []): void
{
    foreach ($answers as $index => $set) {
        Response::factory()->create(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id, 'answers' => $set, 'minutes' => $minutes[$index] ?? null]);
    }
}

/**
 * The statistics of one question.
 *
 * @return array{key: string, presented: int, answered: int, skipped: int, favorable: int|null, midpoint: int|null, flags: list<string>}
 */
function pilotRow(Survey $survey, string $key): array
{
    $rows = array_column(resolve(ItemStatistics::class)->of($survey), null, 'key');

    return $rows[$key];
}

it('counts how many were shown a question, how many answered it and how many skipped it', function (): void {
    pilotResponses($this->survey, [
        ...array_fill(0, 7, ['reward.raises' => 4]),
        ...array_fill(0, 3, ['reward.raises' => null]),
    ]);

    expect(pilotRow($this->survey, 'reward.raises'))->toMatchArray(['presented' => 10, 'answered' => 7, 'skipped' => 3]);
});

it('does not count as skipped a question the person was never shown', function (): void {
    pilotResponses($this->survey, [
        ...array_fill(0, 6, ['manager.respect' => 5, 'reward.pay' => 3]),
        ...array_fill(0, 4, ['reward.pay' => 3]),
    ]);

    expect(pilotRow($this->survey, 'manager.respect'))->toMatchArray(['presented' => 6, 'answered' => 6, 'skipped' => 0])
        ->and(pilotRow($this->survey, 'reward.pay'))->toMatchArray(['presented' => 10, 'skipped' => 0]);
});

it('gives the favorable and the midpoint percents of an agreement question', function (): void {
    pilotResponses($this->survey, [
        ['reward.pay' => 5], ['reward.pay' => 4], ['reward.pay' => 4], ['reward.pay' => 3], ['reward.pay' => 2],
    ]);

    expect(pilotRow($this->survey, 'reward.pay'))->toMatchArray(['answered' => 5, 'favorable' => 60, 'midpoint' => 20, 'flags' => []]);
});

it('flags a question almost everybody agrees with and one that most leave in the middle', function (): void {
    pilotResponses($this->survey, [
        ...array_fill(0, 19, ['reward.pay' => 5, 'reward.raises' => 3]),
        ['reward.pay' => 2, 'reward.raises' => 3],
    ]);

    expect(pilotRow($this->survey, 'reward.pay')['flags'])->toBe(['over_90_favorable'])
        ->and(pilotRow($this->survey, 'reward.raises')['flags'])->toBe(['midpoint_over_40']);
});

it('does not flag exactly 90 percent favorable nor exactly 40 percent at the midpoint', function (): void {
    pilotResponses($this->survey, [
        ...array_fill(0, 9, ['reward.pay' => 4]),
        ['reward.pay' => 1],
    ]);
    pilotResponses($this->survey, [
        ...array_fill(0, 4, ['reward.raises' => 3]),
        ...array_fill(0, 6, ['reward.raises' => 2]),
    ]);

    expect(pilotRow($this->survey, 'reward.pay'))->toMatchArray(['favorable' => 90, 'flags' => []])
        ->and(pilotRow($this->survey, 'reward.raises'))->toMatchArray(['midpoint' => 40, 'flags' => []]);
});

it('prints only the shown and skipped counts of a question under the minimum', function (): void {
    pilotResponses($this->survey, [
        ...array_fill(0, 4, ['reward.pay' => 5]),
        ...array_fill(0, 3, ['reward.pay' => null]),
    ]);

    expect(pilotRow($this->survey, 'reward.pay'))->toMatchArray(['presented' => 7, 'skipped' => 3, 'favorable' => null, 'midpoint' => null, 'flags' => []]);
});

it('gives the median and the 90th percentile of the minutes it took, once there are enough', function (): void {
    pilotResponses($this->survey, array_fill(0, 10, ['reward.pay' => 4]), [5, 6, 7, 8, 9, 10, 11, 12, 13, 14]);

    expect(resolve(ItemStatistics::class)->minutes($this->survey))->toBe(['median' => 9, 'p90' => 13]);
});

it('says nothing of the minutes when there are too few, or none was recorded', function (): void {
    pilotResponses($this->survey, array_fill(0, 4, ['reward.pay' => 4]), [5, 6, 7, 8]);
    expect(resolve(ItemStatistics::class)->minutes($this->survey))->toBeNull();

    pilotResponses($this->survey, array_fill(0, 6, ['reward.pay' => 4]));
    expect(resolve(ItemStatistics::class)->minutes($this->survey))->toBeNull();
});

it('only reads the responses of its own survey', function (): void {
    $other = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'kind' => App\Modules\Engagement\Enums\SurveyKind::Pulse]);
    pilotResponses($other, array_fill(0, 6, ['reward.pay' => 5]));
    pilotResponses($this->survey, array_fill(0, 5, ['reward.pay' => 2]));

    expect(pilotRow($this->survey, 'reward.pay'))->toMatchArray(['presented' => 5, 'favorable' => 0]);
});

it('prints the table of a survey from the command, in its company, and never a comment', function (): void {
    pilotResponses($this->survey, [...array_fill(0, 6, ['reward.pay' => 5]), ['reward.pay' => null]], [8, 9, 10, 11, 12, 13, 14]);
    Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'body' => 'Texto secreto del comentario']);
    $other = Tenant::factory()->create();
    workIn($other);

    $this->artisan('engagement:item-stats', ['survey' => $this->survey->id])
        ->expectsOutputToContain('reward.pay  presented=7 answered=6 skipped=1 favorable=100% midpoint=0% flags=over_90_favorable')
        ->expectsOutputToContain('Minutos: mediana 11, percentil 90 14')
        ->doesntExpectOutputToContain('Texto secreto')
        ->assertSuccessful();
});

it('fails for a survey that does not exist', function (): void {
    $this->artisan('engagement:item-stats', ['survey' => '2f1e9a3c-7a6b-4e0d-9c11-0d6c4f2b8a55'])->assertFailed();
});
