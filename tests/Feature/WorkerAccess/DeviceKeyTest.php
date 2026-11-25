<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\RegisterDeviceKey;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\OfflinePasses;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftDeviceKey;

mutates(RegisterDeviceKey::class);

/*
 * Plan B of the offline marks (spec «Llave del dispositivo y pase sin conexión»): the app makes a key in the phone, the person
 * registers it online after a recent proof, and the server gives a pass for 24 hours. JSON only, with the person's session.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->phone = new SoftDeviceKey;
    // One browser: every request carries the same session cookie, as a phone does (the challenge is tied to the session).
    $this->withCredentials()->withCookie(config()->string('session.cookie'), Str::random(40));
});

/** The person enters with a key (that is a recent proof) and has the browser of this test. */
function enterWithKey(): void
{
    $key = WorkerCredential::factory()->create(['tenant_id' => test()->tenant->id, 'employee_id' => test()->ana->id]);
    enteredAs(test()->ana->id, EntryVia::Key, $key->id);
}

/** The reto the server gives to prove possession of a new key, in base64url. */
function deviceChallenge(): string
{
    return test()->postJson(route('worker.device-key.options', 'acme'))->assertOk()->json('challenge');
}

/**
 * The phone registers its key: it signs the 32 raw bytes of the challenge and sends the public key, the challenge and the signature.
 *
 * @param  array<string, mixed>  $override
 */
function registerDevice(SoftDeviceKey $phone, ?string $challenge = null, array $override = []): TestResponse
{
    $challenge ??= deviceChallenge();

    return test()->postJson(route('worker.device-key.store', 'acme'), [
        'public_key' => $phone->publicKey(),
        'challenge' => $challenge,
        'signature' => $phone->sign(Base64UrlSafe::decodeNoPadding($challenge)),
        'label' => 'Este teléfono',
        ...$override,
    ]);
}

function deviceKeyOf(string $employeeId, ?CarbonImmutable $lastUsedAt = null, ?CarbonImmutable $createdAt = null): WorkerDeviceKey
{
    return WorkerDeviceKey::factory()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employeeId, 'last_used_at' => $lastUsedAt, 'created_at' => $createdAt ?? now()]);
}

it('registers the key of a phone after a possession proof, and answers with the pass', function (): void {
    enterWithKey();
    $challenge = deviceChallenge();

    $response = registerDevice($this->phone, $challenge)->assertCreated();

    $key = WorkerDeviceKey::query()->sole();
    $read = OfflinePasses::read($response->json('pass'));
    expect($response->json())->toHaveKeys(['deviceKeyId', 'pass', 'passId', 'passExpiresAt'])
        ->and($response->json('deviceKeyId'))->toBe($key->id)
        ->and($response->json('passExpiresAt'))->toBe('2026-11-25T15:00:00+00:00')
        ->and($key)->employee_id->toBe($this->ana->id)->label->toBe('Este teléfono')->public_key->toBe($this->phone->publicKey())
        ->revoked_at->toBeNull()->last_used_at->toIso8601String()->toBe('2026-11-24T15:00:00+00:00')
        ->and($read['id'])->toBe($response->json('passId'))->and($read['key'])->toBe($key->id)->and($read['employee'])->toBe($this->ana->id)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('gives the challenge as 32 random bytes in base64url, never twice the same, with no cache', function (): void {
    enterWithKey();

    $first = $this->postJson(route('worker.device-key.options', 'acme'));
    $second = $this->postJson(route('worker.device-key.options', 'acme'));

    expect(strlen(Base64UrlSafe::decodeNoPadding($first->json('challenge'))))->toBe(32)
        ->and($second->json('challenge'))->not->toBe($first->json('challenge'))
        ->and($first->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

describe('the session and the recent proof', function (): void {
    it('answers 401 to all three without a session', function (): void {
        $this->postJson(route('worker.device-key.options', 'acme'))->assertUnauthorized();
        $this->postJson(route('worker.device-key.store', 'acme'), [])->assertUnauthorized();
        $this->postJson(route('worker.device-key.pass', 'acme'), [])->assertUnauthorized();
    });

    it('answers 403 with proofRequired to options and store, not to the renewal, for a session opened with a link', function (): void {
        $key = deviceKeyOf($this->ana->id);
        enteredAs($this->ana->id, EntryVia::IssuedLink);

        $this->postJson(route('worker.device-key.options', 'acme'))->assertForbidden()->assertJson(['proofRequired' => true]);
        $this->postJson(route('worker.device-key.store', 'acme'), [])->assertForbidden()->assertJson(['proofRequired' => true]);
        $this->postJson(route('worker.device-key.pass', 'acme'), ['device_key_id' => $key->id])->assertOk();
    });

    it('asks for the proof again after five minutes', function (): void {
        enterWithKey();
        $this->travel(5)->minutes();

        $this->postJson(route('worker.device-key.options', 'acme'))->assertForbidden()->assertJson(['proofRequired' => true]);
    });
});

describe('the challenge', function (): void {
    it('is taken once: used twice answers 422 the second time', function (): void {
        enterWithKey();
        $challenge = deviceChallenge();

        registerDevice($this->phone, $challenge)->assertCreated();

        registerDevice(new SoftDeviceKey, $challenge)->assertUnprocessable();
        expect(WorkerDeviceKey::query()->count())->toBe(1);
    });

    it('is of one session: another session answers 422', function (): void {
        enterWithKey();
        $challenge = deviceChallenge();
        $this->withCookie(config()->string('session.cookie'), Str::random(40));

        registerDevice($this->phone, $challenge)->assertUnprocessable();
        expect(WorkerDeviceKey::query()->count())->toBe(0);
    });

    it('is good for 60 seconds: 59 pass, 61 do not', function (int $seconds, bool $passes): void {
        enterWithKey();
        $challenge = deviceChallenge();
        $this->travel($seconds)->seconds();

        $passes ? registerDevice($this->phone, $challenge)->assertCreated() : registerDevice($this->phone, $challenge)->assertUnprocessable();
    })->with(['59 seconds' => [59, true], '61 seconds' => [61, false]]);

    it('is one the server never gave', function (): void {
        enterWithKey();

        registerDevice($this->phone, Base64UrlSafe::encodeUnpadded(random_bytes(32)))->assertUnprocessable();
    });

    it('is signed as the 32 raw bytes, not as the text of its base64url', function (): void {
        enterWithKey();
        $challenge = deviceChallenge();

        registerDevice($this->phone, $challenge, ['signature' => $this->phone->sign($challenge)])->assertUnprocessable();
        expect(WorkerDeviceKey::query()->count())->toBe(0);
    });
});

describe('the possession of the key', function (): void {
    it('refuses a signature of another key', function (): void {
        enterWithKey();
        $challenge = deviceChallenge();

        registerDevice($this->phone, $challenge, ['signature' => (new SoftDeviceKey)->sign(Base64UrlSafe::decodeNoPadding($challenge))])
            ->assertUnprocessable()->assertJsonPath('message', 'No pudimos preparar este teléfono. Intente de nuevo.');
    });

    it('refuses a public key that is not P-256', function (): void {
        enterWithKey();
        $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]) ?: throw new RuntimeException;
        $der = Base64UrlSafe::encodeUnpadded((string) base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s/', '', (string) openssl_pkey_get_details($rsa)['key']), true));

        registerDevice($this->phone, null, ['public_key' => $der])->assertUnprocessable();
    });

    it('refuses a badly formed request by validation', function (array $override): void {
        enterWithKey();

        registerDevice($this->phone, null, $override)->assertUnprocessable()->assertJsonValidationErrors(array_keys($override));
    })->with([
        'a public key that is not base64url' => [['public_key' => 'not base64url!']],
        'a public key of 201 characters' => [['public_key' => str_repeat('A', 201)]],
        'no challenge' => [['challenge' => '']],
        'a challenge that is not base64url' => [['challenge' => 'not base64url!']],
        'a signature of 101 characters' => [['signature' => str_repeat('A', 101)]],
        'a label of 61 characters' => [['label' => str_repeat('a', 61)]],
        'a label with a line break' => [['label' => "Mi\nteléfono"]],
    ]);

    it('takes a label of 60 characters', function (): void {
        enterWithKey();

        registerDevice($this->phone, null, ['label' => str_repeat('a', 60)])->assertCreated();
    });
});

describe('the limit of three', function (): void {
    it('replaces the least used when a fourth arrives, never answers 409', function (): void {
        enterWithKey();
        $recent = deviceKeyOf($this->ana->id, now()->subHour());
        $oldest = deviceKeyOf($this->ana->id, now()->subDays(3));
        $middle = deviceKeyOf($this->ana->id, now()->subDay());
        Log::spy();

        $response = registerDevice($this->phone)->assertCreated();

        expect($oldest->refresh())->revoked_reason->toBe('replaced')->revoked_at->toIso8601String()->toBe('2026-11-24T15:00:00+00:00')
            ->and($recent->refresh()->revoked_at)->toBeNull()->and($middle->refresh()->revoked_at)->toBeNull()
            ->and(WorkerDeviceKey::query()->whereNull('revoked_at')->count())->toBe(3)
            ->and(WorkerDeviceKey::query()->whereNull('revoked_at')->pluck('id'))->toContain($response->json('deviceKeyId'));
        Log::shouldHaveReceived('info')->with('worker_access.device_key_registered', ['device_key_id' => $response->json('deviceKeyId'), 'employee_id' => $this->ana->id, 'replaced_id' => $oldest->id]);
    });

    it('counts a key never used by when it was made', function (): void {
        enterWithKey();
        $unused = deviceKeyOf($this->ana->id, null, now()->subDays(10));
        deviceKeyOf($this->ana->id, now()->subDays(2));
        deviceKeyOf($this->ana->id, now()->subDays(1));

        registerDevice($this->phone)->assertCreated();

        expect($unused->refresh()->revoked_reason)->toBe('replaced');
    });

    it('replaces nothing with two active keys: the third is the limit, not past it', function (): void {
        enterWithKey();
        deviceKeyOf($this->ana->id);
        deviceKeyOf($this->ana->id);

        registerDevice($this->phone)->assertCreated();

        expect(WorkerDeviceKey::query()->whereNull('revoked_at')->count())->toBe(3)
            ->and(WorkerDeviceKey::query()->whereNotNull('revoked_at')->count())->toBe(0);
    });

    it('does not count revoked keys nor those of another person', function (): void {
        enterWithKey();
        deviceKeyOf($this->ana->id)->update(['revoked_at' => now(), 'revoked_reason' => 'reissued']);
        $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        $theirs = collect(range(1, 3))->map(fn (): WorkerDeviceKey => deviceKeyOf($beto->id));
        deviceKeyOf($this->ana->id);
        deviceKeyOf($this->ana->id);

        registerDevice($this->phone)->assertCreated();

        expect(WorkerDeviceKey::query()->where('employee_id', $this->ana->id)->whereNull('revoked_at')->count())->toBe(3)
            ->and(WorkerDeviceKey::query()->where('employee_id', $this->ana->id)->where('revoked_reason', 'replaced')->count())->toBe(0)
            ->and($theirs->every(fn (WorkerDeviceKey $key): bool => $key->refresh()->revoked_at === null))->toBeTrue();
    });

    it('follows worker_access.device_keys_max', function (): void {
        enterWithKey();
        config(['worker_access.device_keys_max' => 1]);
        $first = deviceKeyOf($this->ana->id);

        registerDevice($this->phone)->assertCreated();

        expect($first->refresh()->revoked_reason)->toBe('replaced')->and(WorkerDeviceKey::query()->whereNull('revoked_at')->count())->toBe(1);
    });

    it('holds the lock of the person while it counts and writes, so of two registrations at once none leaves four', function (): void {
        deviceKeyOf($this->ana->id);

        DB::enableQueryLog();
        resolve(RegisterDeviceKey::class)->handle($this->ana->id, $this->phone->publicKey(), 'Este teléfono');
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $lock = $queries->search(fn (array $query): bool => str_contains($query['query'], 'pg_advisory_xact_lock') && $query['bindings'] === ["worker_device_keys:{$this->ana->id}"]);
        $count = $queries->search(fn (array $query): bool => str_contains($query['query'], 'from "worker_device_keys"') && str_contains($query['query'], 'count(*)'));
        $insert = $queries->search(fn (array $query): bool => str_starts_with($query['query'], 'insert into "worker_device_keys"'));

        expect($lock)->toBeInt()->and($count)->toBeGreaterThan($lock)->and($insert)->toBeGreaterThan($count);
    });
});

describe('the renewal of the pass', function (): void {
    it('answers the same shape as the registration, with a new pass id, and moves the last use', function (): void {
        enterWithKey();
        $registered = registerDevice($this->phone)->assertCreated();
        $this->travel(25)->minutes();

        $renewed = $this->postJson(route('worker.device-key.pass', 'acme'), ['device_key_id' => $registered->json('deviceKeyId')])->assertOk();

        expect($renewed->json())->toHaveKeys(['deviceKeyId', 'pass', 'passId', 'passExpiresAt'])
            ->and($renewed->json('deviceKeyId'))->toBe($registered->json('deviceKeyId'))
            ->and($renewed->json('passId'))->not->toBe($registered->json('passId'))
            ->and($renewed->json('passExpiresAt'))->toBe('2026-11-25T15:25:00+00:00')
            ->and(OfflinePasses::read($renewed->json('pass'))['id'])->toBe($renewed->json('passId'))
            ->and(WorkerDeviceKey::query()->sole()->last_used_at?->toIso8601String())->toBe('2026-11-24T15:25:00+00:00');
    });

    it('answers 404 for a revoked key, another person\'s and another company\'s', function (Closure $make): void {
        enterWithKey();
        $key = $make($this);

        $this->postJson(route('worker.device-key.pass', 'acme'), ['device_key_id' => $key->id])->assertNotFound();
    })->with([
        'revoked' => [function ($test): WorkerDeviceKey {
            $key = deviceKeyOf($test->ana->id);
            $key->update(['revoked_at' => now(), 'revoked_reason' => 'reissued']);

            return $key;
        }],
        'of another person' => [fn ($test) => deviceKeyOf(EmployeeFactory::new()->create(['tenant_id' => $test->tenant->id])->id)],
        'of another company' => [function ($test): WorkerDeviceKey {
            $other = Tenant::factory()->create();
            $key = WorkerDeviceKey::factory()->create(['tenant_id' => $other->id]);
            workIn($test->tenant);

            return $key;
        }],
    ]);

    it('asks for a key id that is a uuid', function (): void {
        enterWithKey();

        $this->postJson(route('worker.device-key.pass', 'acme'), ['device_key_id' => 'not-a-uuid'])->assertUnprocessable();
        $this->postJson(route('worker.device-key.pass', 'acme'), [])->assertUnprocessable();
    });

    it('writes no log and no audit entry: only the last use moves', function (): void {
        enterWithKey();
        $key = deviceKeyOf($this->ana->id);
        Log::spy();

        $this->postJson(route('worker.device-key.pass', 'acme'), ['device_key_id' => $key->id])->assertOk();

        Log::shouldNotHaveReceived('info');
        expect(AuditEntry::query()->where('event', 'like', 'worker_access.device_key%')->count())->toBe(0);
    });
});

it('writes the registration to the audit trail and the log, with the replaced key when there is one, and never the public key', function (): void {
    enterWithKey();
    Log::spy();

    $first = registerDevice($this->phone)->assertCreated();

    $entry = AuditEntry::query()->where('event', 'worker_access.device_key_registered')->sole();
    expect($entry->subject_id)->toBe($first->json('deviceKeyId'))->and($entry->metadata)->toEqual(['employee_id' => $this->ana->id])
        ->and(json_encode($entry->metadata))->not->toContain($this->phone->publicKey());
    Log::shouldHaveReceived('info')->with('worker_access.device_key_registered', ['device_key_id' => $first->json('deviceKeyId'), 'employee_id' => $this->ana->id])->once();

    foreach ([new SoftDeviceKey, new SoftDeviceKey] as $phone) {
        $this->travel(1)->seconds();
        registerDevice($phone)->assertCreated();
    }
    $this->travel(1)->seconds();
    $fourth = registerDevice(new SoftDeviceKey)->assertCreated();
    $replaced = AuditEntry::query()->where('event', 'worker_access.device_key_registered')->where('subject_id', $fourth->json('deviceKeyId'))->sole();
    expect($replaced->metadata)->toEqual(['employee_id' => $this->ana->id, 'replaced_id' => $first->json('deviceKeyId')]);
});

it('logs a refusal without the key, the challenge or the signature', function (): void {
    enterWithKey();
    $challenge = deviceChallenge();
    Log::spy();

    registerDevice($this->phone, $challenge, ['signature' => (new SoftDeviceKey)->sign(Base64UrlSafe::decodeNoPadding($challenge))])->assertUnprocessable();

    Log::shouldHaveReceived('warning')->with('worker_access.device_key_refused', ['employee_id' => $this->ana->id])->once();
});

it('takes the person from the session, never from what the client sends', function (): void {
    enterWithKey();
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);

    registerDevice($this->phone, null, ['employee_id' => $beto->id, 'tenant_id' => Tenant::factory()->create()->id])->assertCreated();

    expect(WorkerDeviceKey::query()->sole())->employee_id->toBe($this->ana->id)->tenant_id->toBe($this->tenant->id);
});
