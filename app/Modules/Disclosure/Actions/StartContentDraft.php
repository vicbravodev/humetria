<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\DocumentTemplates;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Opens the draft of a block of 5.7 that the company writes by hand (b, c, d or f; criterio C-52): from the latest published
 * text of that block, or else from its base text. One draft at a time per company and block: asking again gives the one that is
 * there. The policy has its own (StartPolicyDraft).
 */
final readonly class StartContentDraft
{
    public function __construct(private CurrentTenant $tenant, private DocumentTemplates $templates) {}

    /**
     * @throws InvalidArgumentException when the kind is the policy.
     */
    public function handle(DocumentKind $kind, ?int $userId): Document
    {
        if (! $kind->isBlock()) {
            throw new InvalidArgumentException('The policy has its own draft.');
        }

        $latest = Document::query()->where('kind', $kind)->where('status', DocumentStatus::Published)->orderByDesc('version')->first();

        $draft = Document::query()->createOrFirst(
            ['kind' => $kind, 'status' => DocumentStatus::Draft],
            ['tenant_id' => $this->tenant->id(), 'content' => $latest->content ?? $this->templates->content($kind), 'created_by' => $userId],
        );

        if ($draft->wasRecentlyCreated) {
            Log::info('disclosure.draft_started', ['document_id' => $draft->id, 'kind' => $kind->value]);
        }

        return $draft;
    }
}
