<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Groups the comments into themes, each with how many comments mention it: what people value and what they would
 * change. It has no tools and no memory, and what it returns is checked by SummaryGuard before it is kept.
 */
#[MaxTokens(1200)]
#[Timeout(60)]
final class CommentSummarizer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function provider(): string
    {
        return config()->string('humi.provider');
    }

    public function model(): string
    {
        return config()->string('humi.model');
    }

    public function instructions(): string
    {
        return (string) file_get_contents(resource_path('ai/engagement-comment-summarizer.system.md'));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $themes = fn () => $schema->array()->items($schema->object([
            'theme' => $schema->string()->required(),
            'count' => $schema->integer()->required(),
        ]))->required();

        return ['valued' => $themes(), 'change' => $themes()];
    }
}
