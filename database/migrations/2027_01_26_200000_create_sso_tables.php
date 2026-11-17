<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The OpenID Connect provider of one company (Entra, Google, Okta): its people with an account sign in through it.
        Schema::create('sso_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('issuer');
            $table->string('client_id');
            // Encrypted by the model (APP_KEY): never logged, audited or sent to a page.
            $table->text('client_secret');
            $table->boolean('enabled')->default(false);
            // Only an enabled connection can be enforced: everyone but the owner (the way out) must then sign in through it.
            $table->boolean('enforced')->default(false);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE sso_connections ADD CONSTRAINT sso_connections_enforced_needs_enabled CHECK (NOT enforced OR enabled)');

        // The email domains a company says are its own. Only a domain whose DNS proved it may let someone in, and only one company
        // at a time may hold a proven domain: otherwise whoever configures an identity provider could sign in as anyone.
        Schema::create('sso_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('domain');
            $table->string('token', 64);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'domain']);
        });

        DB::statement('CREATE UNIQUE INDEX sso_domains_verified_domain_unique ON sso_domains (domain) WHERE verified_at IS NOT NULL');
        DB::statement("ALTER TABLE sso_domains ADD CONSTRAINT sso_domains_domain_lowercase CHECK (domain = lower(domain) AND btrim(domain) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_domains');
        Schema::dropIfExists('sso_connections');
    }
};
