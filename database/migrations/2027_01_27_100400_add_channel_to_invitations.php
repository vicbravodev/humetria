<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The channel a person's survey link went through (`invited_via`, in the invitation and nowhere else: no answers table knows it) and
 * the version of their link (`token_version`, rotated when the mail takes over from WhatsApp so the old link stops working).
 * `no_email` becomes `unreachable`: no way to contact them among the channels the company has on.
 */
return new class extends Migration
{
    private const array TABLES = [
        'campaign_participants' => ['constraint' => 'campaign_participants_invitation_status_check', 'nullable' => false],
        'engagement_invitations' => ['constraint' => 'engagement_invitations_status_known', 'nullable' => true],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $status) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('invited_via', 10)->nullable();
                $blueprint->smallInteger('token_version')->default(0);
            });

            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_invited_via_known CHECK (invited_via IS NULL OR invited_via IN ('mail', 'whatsapp'))");
            $this->statuses($table, $status, "'queued', 'sent', 'failed', 'unreachable'", 'no_email', 'unreachable');
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $status) {
            $this->statuses($table, $status, "'queued', 'sent', 'failed', 'no_email'", 'unreachable', 'no_email');
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_invited_via_known");

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['invited_via', 'token_version']);
            });
        }
    }

    /** @param array{constraint: string, nullable: bool} $status */
    private function statuses(string $table, array $status, string $allowed, string $from, string $to): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$status['constraint']}");
        DB::statement("UPDATE {$table} SET invitation_status = '{$to}' WHERE invitation_status = '{$from}'");
        $null = $status['nullable'] ? 'invitation_status IS NULL OR ' : '';
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$status['constraint']} CHECK ({$null}invitation_status IN ({$allowed}))");
    }
};
