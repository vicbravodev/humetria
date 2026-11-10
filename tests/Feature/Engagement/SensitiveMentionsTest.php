<?php

declare(strict_types=1);

use App\Modules\Engagement\Agents\CommentScreener;
use App\Modules\Engagement\Agents\CommentSummarizer;
use App\Modules\Engagement\Jobs\SummarizeComments;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SensitiveMentions;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

mutates(SensitiveMentions::class);

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    // The filter flags what says «ALARMA»; the summarizer says nothing useful.
    CommentScreener::fake(function (string $prompt): array {
        $block = json_decode((string) preg_replace('~^<comentarios>\n|\n</comentarios>$~', '', $prompt), true, 512, JSON_THROW_ON_ERROR);

        return ['sensitive' => array_values(array_map(fn (array $item): int => $item['n'], array_filter($block, fn (array $item): bool => str_contains($item['texto'], 'ALARMA'))))];
    });
    CommentSummarizer::fake(fn (): array => ['valued' => [], 'change' => []]);
});

/** Twelve comments for a group (the company when null), the first of them a flagged one when `$alarm`. */
function sensitiveGroup(Survey $survey, ?string $group, bool $alarm): void
{
    for ($i = 0; $i < 12; $i++) {
        Comment::factory()->create([
            'tenant_id' => $survey->tenant_id,
            'survey_id' => $survey->id,
            'item_key' => $group === null ? 'open.best' : 'manager.keep',
            'comment_group_id' => $group,
            'body' => $alarm && $i === 0 ? 'ALARMA secreta del grupo' : "Comentario tranquilo {$i}",
        ]);
    }
}

it('tells whoever manages the complaints channel, once, even when two groups have mentions', function (): void {
    sensitiveGroup($this->survey, null, true);
    sensitiveGroup($this->survey, '0d3f1b6c-5d5e-4b0e-8a3d-2f7a9c1d4e10', true);

    SummarizeComments::dispatchSync($this->survey->id, null);
    SummarizeComments::dispatchSync($this->survey->id, '0d3f1b6c-5d5e-4b0e-8a3d-2f7a9c1d4e10');

    Notification::assertSentOnDemandTimes(SensitiveMentions::class, 2);
    foreach ([$this->owner, $this->hr] as $member) {
        Notification::assertSentOnDemand(SensitiveMentions::class, fn (SensitiveMentions $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $member->email);
    }
    Notification::assertNotSentTo($this->viewer, SensitiveMentions::class);
    Notification::assertSentOnDemand(SensitiveMentions::class, fn (SensitiveMentions $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] !== $this->viewer->email);
});

it('sends nothing when no comment was flagged', function (): void {
    sensitiveGroup($this->survey, null, false);

    SummarizeComments::dispatchSync($this->survey->id, null);

    Notification::assertNothingSent();
    expect($this->survey->refresh()->sensitive_mentions)->toBeFalse();
});

it('carries no comment, no count and no name: only the survey, the advice and the way to the channel', function (): void {
    $company = new CompanySummary($this->tenant->id, 'Autopartes Demo', $this->tenant->slug, false);
    $mail = (new SensitiveMentions($company, 'Clima · noviembre 2026', route('tenant.complaints.channel.show', $this->tenant->slug)))->toMail(new AnonymousNotifiable);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Hubo menciones de posible acoso o violencia en los comentarios')
        ->and($mail->actionText)->toBe('Abrir el canal de quejas')
        ->and($mail->actionUrl)->toBe(route('tenant.complaints.channel.show', $this->tenant->slug))
        ->and($html)->toContain('En los comentarios de Clima · noviembre 2026 hubo menciones de este tipo.')
        ->toContain('Te recomendamos difundir el canal de quejas entre tus personas.')
        ->not->toMatch('/\d+ comentarios?/');
});
