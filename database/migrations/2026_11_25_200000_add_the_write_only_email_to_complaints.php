<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-035, complaints channel: the address a person may leave, even in an anonymous complaint, only so that the system can tell
 * them there is news in their case. Nobody ever reads it (not even the handlers): the model encrypts it, no page carries it, and
 * it is erased thirty days after the case closes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE complaints ADD COLUMN reporter_email text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE complaints DROP COLUMN reporter_email');
    }
};
