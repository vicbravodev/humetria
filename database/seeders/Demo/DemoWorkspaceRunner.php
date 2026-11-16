<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Assistant\Models\ChatConversation;
use App\Modules\Assistant\Models\ChatMessage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Closure;
use Database\Seeders\Demo\Stories\AttendanceStory;
use Database\Seeders\Demo\Stories\CompensationStory;
use Database\Seeders\Demo\Stories\ComplaintsStory;
use Database\Seeders\Demo\Stories\DisclosureStory;
use Database\Seeders\Demo\Stories\EngagementStory;
use Database\Seeders\Demo\Stories\LeaveStory;
use Database\Seeders\Demo\Stories\Nom035Story;
use Database\Seeders\Demo\Stories\OrganizationStory;
use Database\Seeders\Demo\Stories\SeatingStory;
use Database\Seeders\Demo\Stories\TeleworkStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * Tells the demo's story, one module at a time and in this order: each story stands on what the ones before it built.
 * Preparing again wipes the company first and tells the same story on it: its row, slug and members' accounts stay, and
 * the people get the same ids, so the links and QR codes the console shared still work. The door's people get new ones: a
 * portal link an earlier audience kept stops working.
 */
final readonly class DemoWorkspaceRunner implements DemoWorkspace
{
    public function __construct(private OrganizationStory $organization, private Nom035Story $nom035, private EngagementStory $engagement, private AttendanceStory $attendance, private LeaveStory $leave, private ComplaintsStory $complaints, private DisclosureStory $disclosure, private CompensationStory $compensation, private SeatingStory $seating, private TeleworkStory $telework, private Companies $companies, private DemoCompanies $demos) {}

    /** Tables with a tenant_id the reset keeps: the audit trail is insert-only, and a demo request is a lead, not content. */
    private const array KEPT = ['audit_entries', 'demo_requests'];

    public function prepare(?Closure $step = null): void
    {
        $step ??= static function (string $label): void {};

        if ($this->demos->id() !== null) {
            $step('Borrando la demo');
            $this->reset();
        }

        $tenant = $this->organization->company();
        // From here on every action works inside the demo company: its data, logs and audit entries.
        $this->companies->enter($tenant->id);
        $context = $this->organization->members($tenant);

        foreach ($this->stories() as $label => $story) {
            $step($label);
            $story->tell($context);
        }

        Log::info('demo.prepared', ['tenant_id' => $tenant->id]);
    }

    public function reset(): void
    {
        $tenantId = $this->demos->id();

        if ($tenantId === null) {
            return;
        }

        // Only the demo's rows can go: the insert-only tables refuse to delete any other company's (migration 2027_01_19).
        $rows = DB::transaction(function () use ($tenantId): int {
            $participants = ConversationParticipant::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->select('id');
            $morph = (new ConversationParticipant)->getMorphClass();
            $rows = ChatMessage::query()->where('participant_type', $morph)->whereIn('participant_id', $participants)->toBase()->delete()
                + ChatConversation::query()->where('participant_type', $morph)->whereIn('participant_id', $participants)->toBase()->delete();

            foreach ($this->tablesInDeleteOrder() as $table) {
                $rows += DB::table($table)->where('tenant_id', $tenantId)->delete();
            }

            return $rows;
        });

        // Every module keeps its files under {kind}/{company}/ on its disk.
        foreach (array_filter(config()->array('billing.storage_disks'), is_string(...)) as $key) {
            $disk = Storage::disk(config()->string("filesystems.{$key}"));

            foreach ($disk->directories() as $kind) {
                $disk->deleteDirectory("{$kind}/{$tenantId}");
            }
        }

        Log::info('demo.reset', ['tenant_id' => $tenantId, 'rows' => $rows]);
    }

    /**
     * Every table with a tenant_id, read from the database so a new one is never forgotten, each after the tables whose
     * foreign keys point at it.
     *
     * @return list<string>
     */
    private function tablesInDeleteOrder(): array
    {
        $tables = DB::table('information_schema.columns')->where('table_schema', DB::raw('current_schema()'))->where('column_name', 'tenant_id')->pluck('table_name')->all();
        $left = array_diff_key(array_flip(array_filter($tables, is_string(...))), array_flip(self::KEPT));
        $referencedBy = [];

        foreach (DB::table('pg_constraint')->where('contype', 'f')->whereColumn('conrelid', '<>', 'confrelid')->selectRaw("conrelid::regclass::text || ' ' || confrelid::regclass::text as edge")->pluck('edge') as $edge) {
            if (is_string($edge)) {
                $referencedBy[Str::after($edge, ' ')][Str::before($edge, ' ')] = true;
            }
        }

        $ordered = [];

        while ($left !== []) {
            $free = array_filter(array_keys($left), fn (string $table): bool => array_intersect_key($referencedBy[$table] ?? [], $left) === []);

            if ($free === []) {
                throw new LogicException('The tables with a tenant_id point at each other in a cycle: no order deletes them.');
            }

            foreach ($free as $table) {
                $ordered[] = $table;
                unset($left[$table]);
            }
        }

        return $ordered;
    }

    /** @return array<string, DemoStory> each story under the step the console shows while it runs */
    private function stories(): array
    {
        return [
            'Organización' => $this->organization,
            'NOM-035' => $this->nom035,
            'Clima y pulso' => $this->engagement,
            'Asistencia' => $this->attendance,
            'Vacaciones' => $this->leave,
            'Denuncias' => $this->complaints,
            'Difusión' => $this->disclosure,
            'Compensación' => $this->compensation,
            'Ley Silla' => $this->seating,
            'Teletrabajo' => $this->telework,
        ];
    }
}
