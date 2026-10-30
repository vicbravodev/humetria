<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where each person's survey link stands now that a queue sends it: waiting in a batch, sent, failed, or not sendable
 * for lack of an email. The batch is the last one that queued them: only that batch's job sends their link, so an
 * older job retried late never sends it twice. The campaign keeps its last batch, to show how its sending goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_participants', function (Blueprint $table) {
            $table->string('invitation_status', 20)->nullable();
            $table->uuid('invitation_batch')->nullable();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->uuid('invitation_batch')->nullable();
        });

        DB::statement("ALTER TABLE campaign_participants ADD CONSTRAINT campaign_participants_invitation_status_check CHECK (invitation_status IN ('queued', 'sent', 'failed', 'no_email'))");
        DB::statement("UPDATE campaign_participants SET invitation_status = 'sent' WHERE invited_at IS NOT NULL");
        DB::statement("UPDATE campaign_participants SET invitation_status = 'no_email' FROM campaigns WHERE campaigns.id = campaign_participants.campaign_id AND campaigns.status <> 'draft' AND campaign_participants.invited_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('invitation_batch');
        });

        Schema::table('campaign_participants', function (Blueprint $table) {
            $table->dropColumn(['invitation_status', 'invitation_batch']);
        });
    }
};
