<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use Carbon\CarbonImmutable;

/**
 * The ticket a phone keeps to mark without a connection (criterio 3.3.1): `v1.{payload}.{hmac}`, 235 characters. The payload
 * names the company, the person, the second it was issued (rounded up, so it is never dated before the mark made with a
 * connection that handed it out) and 16 random bytes; the HMAC-SHA256 is over its own domain with the app key, and
 * verifies with each of APP_PREVIOUS_KEYS too, so rotating the key keeps the tickets. On its own a ticket proves nothing:
 * it is the lower bound of the time of a mark that is signed apart, and the random value makes it good for one mark only.
 * It is a class of its own and not a shared signer: it is tied to this module's rules, as the complaints' is to its channel.
 */
final readonly class OfflineTicket
{
    private const string VERSION = 'v1';

    public function __construct(private CurrentTenant $tenant, private AgreementStatus $agreements, private Timezones $timezones) {}

    /**
     * A new batch for a person, each with the second it was issued.
     *
     * @return list<array{ticket: string, issuedAt: int}>
     */
    public function batch(string $employeeId): array
    {
        $issuedAt = CarbonImmutable::now()->ceilSecond()->getTimestamp();
        $batch = [];

        foreach (range(1, max(1, config()->integer('attendance.offline.ticket_batch'))) as $ignored) {
            $batch[] = ['ticket' => $this->issue($employeeId, $issuedAt), 'issuedAt' => $issuedAt];
        }

        return $batch;
    }

    /**
     * The person, the moment and the random value of a ticket the app signed for the current company; null when its shape
     * does not serve, no key verifies it or it is another company's. Its age is not looked at here.
     *
     * @return array{employee_id: string, issued_at: CarbonImmutable, nonce: string}|null
     */
    public function verify(string $ticket): ?array
    {
        $parts = explode('.', $ticket);

        if (count($parts) !== 3 || $parts[0] !== self::VERSION || ! $this->signedByAKey($parts[1], $parts[2])) {
            return null;
        }

        $payload = json_decode(self::decode($parts[1]), true);

        if (! is_array($payload) || ! is_string($payload['t'] ?? null) || ! is_string($payload['e'] ?? null) || ! is_int($payload['i'] ?? null)
            || ! is_string($payload['n'] ?? null) || preg_match('/^[0-9a-f]{32}\z/', $payload['n']) !== 1 || $payload['t'] !== $this->tenant->id()) {
            return null;
        }

        return ['employee_id' => $payload['e'], 'issued_at' => CarbonImmutable::createFromTimestampUTC($payload['i']), 'nonce' => $payload['n']];
    }

    /**
     * What the phone needs to mark without a connection, besides the tickets: who it is, where it works, which version of
     * the agreement they accepted (null if not the current one), the relying party its passkey signs for, and the only
     * switch the phone reads (`attendance.offline.enabled`), and the marks that ask this person for the QR (as the page of Marcar says).
     *
     * @return array{tenantId: string, employeeId: string, centerId: string, agreementVersion: int|null, rpId: string, offline: bool, qrTypes: list<string>}
     */
    public function context(RosterEntry $person): array
    {
        return [
            'tenantId' => $this->tenant->id(),
            'employeeId' => $person->id,
            'centerId' => $person->workCenterId,
            'agreementVersion' => $this->agreements->acceptedVersion($person->id),
            // The same source as the ceremony that makes the passkeys.
            'rpId' => config()->string('fortify.passkeys.relying_party_id'),
            'offline' => config()->boolean('attendance.offline.enabled'),
            'qrTypes' => array_map(fn (MarkType $type): string => $type->value, MarkPolicyCheck::qrTypesOf($person, $this->timezones->forCenter($person->workCenterId))),
        ];
    }

    private function issue(string $employeeId, int $issuedAt): string
    {
        $payload = self::encode(json_encode(['t' => $this->tenant->id(), 'e' => $employeeId, 'i' => $issuedAt, 'n' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));

        return self::VERSION.".{$payload}.".self::sign($payload, config()->string('app.key'));
    }

    private function signedByAKey(string $payload, string $mac): bool
    {
        foreach ($this->keys() as $key) {
            if (hash_equals(self::sign($payload, $key), $mac)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The app key first (it signs), then the previous ones (they only verify): the same as the links of Disclosure.
     *
     * @return list<string>
     */
    private function keys(): array
    {
        return array_values(array_filter([config()->string('app.key'), ...config()->array('app.previous_keys')], is_string(...)));
    }

    private static function sign(string $payload, string $key): string
    {
        return self::encode(hash_hmac('sha256', 'attendance-ticket|'.self::VERSION."|{$payload}", $key, true));
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $base64url): string
    {
        return base64_decode(strtr($base64url, '-_', '+/'), true) ?: '';
    }
}
