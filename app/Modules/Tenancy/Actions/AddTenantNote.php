<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantNote;
use Illuminate\Support\Facades\Log;

/** Writes down an internal note of the staff about a company. The log says that one was written, never what it says. */
final class AddTenantNote
{
    public function handle(Tenant $tenant, User $author, string $body): TenantNote
    {
        $note = TenantNote::query()->create(['tenant_id' => $tenant->id, 'author_id' => $author->id, 'body' => $body]);

        Log::info('tenancy.note_added', ['tenant_id' => $tenant->id, 'note_id' => $note->id]);

        return $note;
    }
}
