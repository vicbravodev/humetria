<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The invitation to the portal (WorkerAccess) is followed like a survey invitation: its link is the invitation and the source.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->checks("'campaign_participant', 'engagement_invitation', 'worker_access_link'", "'campaign', 'engagement_survey', 'worker_access'");
    }

    public function down(): void
    {
        // Fails while a row of the portal invitation exists: its history is insert-only, so it is not deleted here.
        $this->checks("'campaign_participant', 'engagement_invitation'", "'campaign', 'engagement_survey'");
    }

    private function checks(string $invitationTypes, string $sourceTypes): void
    {
        DB::statement('ALTER TABLE messaging_deliveries DROP CONSTRAINT messaging_deliveries_invitation_type_check');
        DB::statement('ALTER TABLE messaging_deliveries DROP CONSTRAINT messaging_deliveries_source_type_check');
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_invitation_type_check CHECK (invitation_type IN ({$invitationTypes}))");
        DB::statement("ALTER TABLE messaging_deliveries ADD CONSTRAINT messaging_deliveries_source_type_check CHECK (source_type IN ({$sourceTypes}))");
    }
};
