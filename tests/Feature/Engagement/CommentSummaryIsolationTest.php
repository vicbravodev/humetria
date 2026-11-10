<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Models\Tenant;

it('does not read or change the comment summaries of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $survey = Survey::factory()->closed()->create(['tenant_id' => $b->id]);
    $summaryOfB = CommentSummary::factory()->create(['tenant_id' => $b->id, 'survey_id' => $survey->id]);

    workIn($a);
    expect(CommentSummary::query()->count())->toBe(0)
        ->and(CommentSummary::query()->find($summaryOfB->id))->toBeNull()
        ->and(CommentSummary::query()->whereKey($summaryOfB->id)->update(['generated_on' => '2020-01-01']))->toBe(0)
        ->and(CommentSummary::query()->whereKey($summaryOfB->id)->delete())->toBe(0);

    workIn($b);
    expect(CommentSummary::query()->sole()->generated_on?->toDateString())->not->toBe('2020-01-01');
});
