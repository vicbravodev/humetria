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
 * Reads the block of comments and says which describe harassment or violence, by their number. It has no tools and no
 * memory: it only classifies, and the code takes the flagged comments out before anything is summarized.
 */
#[MaxTokens(500)]
#[Timeout(60)]
final class CommentScreener implements Agent, HasStructuredOutput
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
        return (string) file_get_contents(resource_path('ai/engagement-comment-screener.system.md'));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['sensitive' => $schema->array()->items($schema->integer())->required()];
    }
}
