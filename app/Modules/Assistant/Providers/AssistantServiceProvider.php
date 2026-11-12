<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Providers;

use App\Modules\Assistant\Console\EvaluateAssistant;
use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Assistant\Listeners\DeleteMemberConversations;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\ComplianceReaders;
use App\Modules\Assistant\Support\Readers\Nom035Reader;
use App\Modules\Assistant\Support\Readers\Nom037Reader;
use App\Modules\Assistant\Support\TenantAiAccess;
use App\Modules\Assistant\Support\TenantHumiActivity;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Assistant\Tools\GetCampaignProgress;
use App\Modules\Assistant\Tools\GetComplianceOverview;
use App\Modules\Assistant\Tools\GetFirstSteps;
use App\Modules\Assistant\Tools\GetRiskResults;
use App\Modules\Assistant\Tools\ListPendingActions;
use App\Modules\Assistant\Tools\SearchRegulations;
use App\Modules\Tenancy\Events\MemberRemoved;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Humi, the AI assistant. Its routes live in routes/assistant.php, required from the company group in routes/company.php.
 */
final class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiAccess::class, TenantAiAccess::class);
        $this->app->bind(HumiActivity::class, TenantHumiActivity::class);

        // One reader per norm, in the order Humi tells them: a new norm (Ley Silla, violencia laboral…) is one more here.
        $this->app->singleton(ComplianceReaders::class, fn (Application $app): ComplianceReaders => new ComplianceReaders([
            $app->make(Nom035Reader::class),
            $app->make(Nom037Reader::class),
        ], [Nom035Reader::class => 'nom035', Nom037Reader::class => 'nom037']));

        // The tools of Humi: a new module adds its own here.
        $this->app->singleton(ToolRegistry::class, fn (Application $app): ToolRegistry => new ToolRegistry($app, [
            GetComplianceOverview::class,
            GetCampaignProgress::class,
            GetRiskResults::class,
            ListPendingActions::class,
            SearchRegulations::class,
            GetFirstSteps::class,
        ], [
            // Regulations are not a module of the company: Humi always has them. A tool that reads two norms stays with either.
            GetComplianceOverview::class => ['nom035', 'nom037'],
            GetCampaignProgress::class => ['nom035'],
            GetRiskResults::class => ['nom035'],
            ListPendingActions::class => ['nom035', 'nom037'],
        ]));
    }

    public function boot(): void
    {
        // The SDK stores the participant's class in each conversation: an alias, so renaming the class keeps the history.
        Relation::morphMap(['assistant_participant' => ConversationParticipant::class]);

        Event::listen(MemberRemoved::class, DeleteMemberConversations::class);

        if ($this->app->runningInConsole()) {
            $this->commands([EvaluateAssistant::class]);
        }
    }
}
