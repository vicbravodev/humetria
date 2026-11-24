import type { MarkType } from '@/lib/attendance/next-step';

/*
 * The challenge of a mark made without a connection, version 1: the string the phone builds, hashes and has signed, and
 * that the server builds again from what the phone sends (App\Modules\Attendance\Support\OfflineChallenge). Both sides
 * are held to the same vectors: tests/Fixtures/attendance/offline-challenge-v1.json.
 */

export type ChallengePosition = {
    latE5: number;
    lngE5: number;
    accuracyM: number;
};

export type ChallengeFields = {
    tenantId: string;
    employeeId: string;
    centerId: string;
    type: MarkType;
    qr: string | null;
    /** Unix seconds. */
    declaredAt: number;
    position: ChallengePosition | null;
    agreementVersion: number;
    seq: number;
    passId: string | null;
    ticket: string;
};

/** The position as the integers that are signed and sent: the server derives the degrees from them. */
export function positionOf(coords: {
    latitude: number;
    longitude: number;
    accuracy: number;
}): ChallengePosition {
    return {
        latE5: Math.round(coords.latitude * 1e5),
        lngE5: Math.round(coords.longitude * 1e5),
        accuracyM: Math.round(coords.accuracy),
    };
}

/** Whether the fields can be written without ambiguity: whole numbers, a ticket and no `|` in any word. */
function writable(fields: ChallengeFields): boolean {
    const { position } = fields;
    const numbers = [
        fields.declaredAt,
        fields.agreementVersion,
        fields.seq,
        ...(position === null
            ? []
            : [position.latE5, position.lngE5, position.accuracyM]),
    ];
    const words = [
        fields.tenantId,
        fields.employeeId,
        fields.centerId,
        fields.qr ?? '',
        fields.passId ?? '',
        fields.ticket,
    ];

    return (
        fields.ticket !== '' &&
        numbers.every((value) => Number.isInteger(value)) &&
        !words.some((word) => word.includes('|'))
    );
}

/** The three position fields, or three empty ones: never zeros. */
function positionFields(position: ChallengePosition | null): (number | '')[] {
    return position === null
        ? ['', '', '']
        : [position.latE5, position.lngE5, position.accuracyM];
}

/**
 * The string to sign; null when it cannot be one: a field with a `|` would shift the ones after it, a number that is
 * not an integer has no single spelling and a mark without a ticket is no mark.
 */
export function canonicalText(fields: ChallengeFields): string | null {
    if (!writable(fields)) {
        return null;
    }

    return [
        'humetria-offline-mark-v1',
        fields.tenantId,
        fields.employeeId,
        fields.centerId,
        fields.type,
        fields.qr === null ? 'declared_interval' : 'qr_window',
        fields.qr ?? '',
        fields.declaredAt,
        ...positionFields(fields.position),
        fields.agreementVersion,
        fields.seq,
        fields.passId ?? '',
        fields.ticket,
    ].join('|');
}

/** The 32 raw bytes of the SHA-256 of the text: what a passkey signs as its challenge and a device key as its message. */
export async function challengeHash(
    text: string,
): Promise<Uint8Array<ArrayBuffer>> {
    return new Uint8Array(
        await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)),
    );
}
