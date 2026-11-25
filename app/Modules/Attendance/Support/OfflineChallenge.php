<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use InvalidArgumentException;

/**
 * The challenge a phone signs when it marks without connection (spec «Reto canónico v1»): the passkey or the device key signs
 * the 32 raw bytes of the SHA-256 of one canonical string, and the server rebuilds the string from the delivered fields to
 * verify. The phone builds the same string in TypeScript; tests/Fixtures/attendance/offline-challenge-v1.json fixes it for both.
 *
 * The `v1` prefix lets the format change without breaking deliveries still waiting in a phone. Nothing is rounded here: the
 * position and its accuracy are the integers the phone sent, and the three go empty together without one.
 */
final class OfflineChallenge
{
    private const string PREFIX = 'humetria-offline-mark-v1';

    /**
     * The canonical string. `basis` is `qr_window` when the element carries a QR and `declared_interval` when it does not, by
     * the presence of the field alone: what the phone knew when it signed. The basis the server keeps is decided apart and may
     * be lower (an old QR).
     *
     * @throws InvalidArgumentException when a field holds a `|`, which would shift the ones after it
     */
    public static function canonical(
        string $tenantId,
        string $employeeId,
        string $workCenterId,
        MarkType $type,
        ?string $qr,
        int $declaredAt,
        ?int $latE5,
        ?int $lngE5,
        ?int $accuracyM,
        int $agreementVersion,
        int $seq,
        ?string $passId,
        string $ticket,
    ): string {
        $fields = [
            self::PREFIX, $tenantId, $employeeId, $workCenterId, $type->value, $qr === null ? 'declared_interval' : 'qr_window',
            $qr ?? '', (string) $declaredAt, (string) $latE5, (string) $lngE5, (string) $accuracyM,
            (string) $agreementVersion, (string) $seq, $passId ?? '', $ticket,
        ];

        foreach ($fields as $field) {
            if (str_contains($field, '|')) {
                throw new InvalidArgumentException('A field of the challenge holds a pipe.');
            }
        }

        return implode('|', $fields);
    }

    /**
     * The 32 raw bytes of the SHA-256 of the string: the `$expected` of `WorkerSignatures::verifyChallenge` and of
     * `DeviceKeySignatures::verify`. A device key signs these same bytes with ECDSA (WebCrypto and `openssl_verify` each
     * apply their own SHA-256 on top).
     */
    public static function hash(string $canonical): string
    {
        return hash('sha256', $canonical, true);
    }
}
