<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Providers;

use App\Modules\Assistant\Console\EvaluateAssistant;
use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Assistant\Listeners\DeleteMemberConversations;
use App\Modules\Assistant\Listeners\SendLlmAnalytics;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\ComplianceReaders;
use App\Modules\Assistant\Support\Readers\AttendanceReader;
use App\Modules\Assistant\Support\Readers\ClimateReader;
use App\Modules\Assistant\Support\Readers\CompensationReader;
use App\Modules\Assistant\Support\Readers\ComplaintsReader;
use App\Modules\Assistant\Support\Readers\Nom035Reader;
use App\Modules\Assistant\Support\Readers\Nom037Reader;
use App\Modules\Assistant\Support\Readers\OrgChartReader;
use App\Modules\Assistant\Support\Readers\PulseReader;
use App\Modules\Assistant\Support\Readers\SeatingReader;
use App\Modules\Assistant\Support\Readers\VacationsReader;
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
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;

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
            $app->make(SeatingReader::class),
            $app->make(VacationsReader::class),
            $app->make(ComplaintsReader::class),
            $app->make(PulseReader::class),
            $app->make(ClimateReader::class),
            $app->make(AttendanceReader::class),
            $app->make(OrgChartReader::class),
            // Every company has Compensación: it is in no module of the company, so it is told to all.
            $app->make(CompensationReader::class),
        ], [Nom035Reader::class => 'nom035', Nom037Reader::class => 'nom037', SeatingReader::class => 'seating', VacationsReader::class => 'vacations', ComplaintsReader::class => 'complaints', PulseReader::class => 'pulse', ClimateReader::class => 'climate', AttendanceReader::class => 'attendance', OrgChartReader::class => 'org_chart']));

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
            GetComplianceOverview::class => ['nom035', 'nom037', 'seating', 'vacations', 'complaints', 'pulse', 'climate', 'attendance', 'org_chart'],
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

        // One instance per process: it remembers when each call to a model started, to tell PostHog how long it took.
        $this->app->singleton(SendLlmAnalytics::class);
        Event::listen([PromptingAgent::class, StreamingAgent::class], [SendLlmAnalytics::class, 'started']);
        // Both by name: Laravel matches a listener to an event's interfaces, not to its parent class.
        Event::listen([AgentPrompted::class, AgentStreamed::class], [SendLlmAnalytics::class, 'answered']);
        Event::listen(AgentFailed::class, [SendLlmAnalytics::class, 'failed']);

        if ($this->app->runningInConsole()) {
            $this->commands([EvaluateAssistant::class]);
        }
    }
}
