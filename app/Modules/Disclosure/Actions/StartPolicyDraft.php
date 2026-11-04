<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\DocumentTemplates;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * Opens the draft of the policy (criterios C-50, C-51): from the latest published version when there is one («nueva versión
 * desde la vigente»), or else from the example of Guía IV. There is one draft at a time per company: asking again gives
 * the one that is there.
 */
final readonly class StartPolicyDraft
{
    public function __construct(private CurrentTenant $tenant, private DocumentTemplates $templates, private Nom035Policy $policy) {}

    public function handle(?int $userId): Document
    {
        $latest = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published)->orderByDesc('version')->first();

        // `createOrFirst`: if two requests race, the loser reads the winner's draft instead of failing on the unique index.
        $draft = Document::query()->createOrFirst(
            ['kind' => DocumentKind::Policy, 'status' => DocumentStatus::Draft],
            [
                'tenant_id' => $this->tenant->id(),
                'content' => $latest->content ?? $this->templates->content(DocumentKind::Policy),
                'template_version' => $latest->template_version ?? $this->policy->version(),
                'created_by' => $userId,
            ],
        );

        if ($draft->wasRecentlyCreated) {
            Log::info('disclosure.draft_started', ['document_id' => $draft->id, 'kind' => DocumentKind::Policy->value]);
        }

        return $draft;
    }
}
