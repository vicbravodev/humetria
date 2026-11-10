<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Response;
use App\Modules\Tenancy\Models\Tenant;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('fails when a prop carries the id of a stored response', function (): void {
    $survey = climateResultsSurvey($this->tenant, 1);
    $id = Response::query()->where('survey_id', $survey->id)->sole()->id;

    expect(fn () => expectNoEngagementResponses(['rows' => [['ref' => $id]]]))->toThrow(ExpectationFailedException::class);
});

it('fails when a prop carries the id or the text of a stored comment', function (): void {
    $survey = climateResultsSurvey($this->tenant, 1);
    $comment = Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'body' => 'Falta personal en la bodega']);

    expect(fn () => expectNoEngagementResponses(['c' => $comment->id]))->toThrow(ExpectationFailedException::class)
        ->and(fn () => expectNoEngagementResponses(['quote' => 'Falta personal en la bodega']))->toThrow(ExpectationFailedException::class);
});

it('fails when a prop is a map from a question key to a number, or a map named answers', function (): void {
    expect(fn () => expectNoEngagementResponses(['person' => ['clarity.goals' => 4]]))->toThrow(ExpectationFailedException::class)
        ->and(fn () => expectNoEngagementResponses(['answers' => ['x' => 'y']]))->toThrow(ExpectationFailedException::class);
});

it('lets aggregates through, a question key as a value included', function (): void {
    climateResultsSurvey($this->tenant, 1);

    expectNoEngagementResponses(['items' => [['key' => 'clarity.goals', 'favorable' => 70, 'answers_count' => 12]], 'index' => 4]);

    expect(true)->toBeTrue();
});
