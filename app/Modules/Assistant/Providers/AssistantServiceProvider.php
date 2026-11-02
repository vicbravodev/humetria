<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Providers;

use App\Modules\Assistant\Listeners\DeleteMemberConversations;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Events\MemberRemoved;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Humi, the AI assistant. Its routes live in routes/assistant.php, required from the company group in routes/company.php.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The SDK stores the participant's class in each conversation: an alias, so renaming the class keeps the history.
        Relation::morphMap(['assistant_participant' => ConversationParticipant::class]);

        Event::listen(MemberRemoved::class, DeleteMemberConversations::class);
    }
}
