<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A visit or a remote check decides the list the person sent too, so it leaves the inbox: such a review may carry the
 * list. A checklist review still needs one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE workplace_reviews DROP CONSTRAINT workplace_reviews_checklist_has_application');
        DB::statement("ALTER TABLE workplace_reviews ADD CONSTRAINT workplace_reviews_checklist_has_application CHECK (method <> 'checklist' OR application_id IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE workplace_reviews DROP CONSTRAINT workplace_reviews_checklist_has_application');
        DB::statement("ALTER TABLE workplace_reviews ADD CONSTRAINT workplace_reviews_checklist_has_application CHECK ((method = 'checklist') = (application_id IS NOT NULL))");
    }
};
