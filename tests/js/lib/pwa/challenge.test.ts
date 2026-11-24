import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { canonicalText, challengeHash, positionOf } from '@/lib/pwa/challenge';
import type { ChallengeFields } from '@/lib/pwa/challenge';

type Input = {
    tenant_id: string;
    employee_id: string;
    work_center_id: string;
    mark_type: ChallengeFields['type'];
    qr: string | null;
    declared_at: number;
    lat_e5: number | null;
    lng_e5: number | null;
    accuracy_m: number | null;
    agreement_version: number;
    seq: number;
    pass_id: string | null;
    ticket: string;
};

type Vector = {
    name: string;
    input: Input;
    canonical: string;
    sha256_hex: string;
};

/** The vectors PHP checks too: tests/Unit/Attendance/OfflineChallengeTest.php. */
const fixture: { valid: Vector[]; invalid: Pick<Vector, 'name' | 'input'>[] } =
    JSON.parse(
        readFileSync(
            'tests/Fixtures/attendance/offline-challenge-v1.json',
            'utf8',
        ),
    );

function fieldsOf(input: Input): ChallengeFields {
    return {
        tenantId: input.tenant_id,
        employeeId: input.employee_id,
        centerId: input.work_center_id,
        type: input.mark_type,
        qr: input.qr,
        declaredAt: input.declared_at,
        position:
            input.lat_e5 === null ||
            input.lng_e5 === null ||
            input.accuracy_m === null
                ? null
                : {
                      latE5: input.lat_e5,
                      lngE5: input.lng_e5,
                      accuracyM: input.accuracy_m,
                  },
        agreementVersion: input.agreement_version,
        seq: input.seq,
        passId: input.pass_id,
        ticket: input.ticket,
    };
}

const [first] = fixture.valid;

if (first === undefined) {
    throw new Error('The challenge fixture has no vectors.');
}

const base = fieldsOf(first.input);

function hex(bytes: Uint8Array): string {
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join(
        '',
    );
}

describe('canonicalText and challengeHash', () => {
    it('give, for every vector, the string and the SHA-256 that PHP gives', async () => {
        expect(fixture.valid.length).toBeGreaterThanOrEqual(6);

        for (const vector of fixture.valid) {
            const text = canonicalText(fieldsOf(vector.input));

            expect(text, vector.name).toBe(vector.canonical);
            expect(
                hex(await challengeHash(vector.canonical)),
                vector.name,
            ).toBe(vector.sha256_hex);
        }
    });

    it('refuses the strings PHP refuses', () => {
        expect(fixture.invalid.length).toBeGreaterThanOrEqual(2);

        for (const vector of fixture.invalid) {
            expect(
                canonicalText(fieldsOf(vector.input)),
                vector.name,
            ).toBeNull();
        }
    });

    it('puts the pass id before the ticket and leaves it empty without one', () => {
        expect(canonicalText({ ...base, passId: 'pass-1' })).toMatch(
            /\|pass-1\|v1\.cGF5bG9hZA\.aG1hYw$/,
        );
        expect(canonicalText({ ...base, passId: null })).toMatch(
            /\|\|v1\.cGF5bG9hZA\.aG1hYw$/,
        );
    });

    it('is refused when any field holds a pipe', () => {
        expect(canonicalText({ ...base, tenantId: 'a|b' })).toBeNull();
        expect(canonicalText({ ...base, employeeId: 'a|b' })).toBeNull();
        expect(canonicalText({ ...base, centerId: 'a|b' })).toBeNull();
        expect(canonicalText({ ...base, passId: 'a|b' })).toBeNull();
        expect(canonicalText({ ...base, ticket: 'a|b' })).toBeNull();
    });

    it('is refused for a number that is not an integer', () => {
        expect(canonicalText({ ...base, declaredAt: 1.5 })).toBeNull();
        expect(canonicalText({ ...base, seq: Number.NaN })).toBeNull();
        expect(canonicalText({ ...base, agreementVersion: 3.2 })).toBeNull();
        expect(
            canonicalText({
                ...base,
                position: { latE5: 1.5, lngE5: 1, accuracyM: 1 },
            }),
        ).toBeNull();
    });

    it('is refused without a ticket', () => {
        expect(canonicalText({ ...base, ticket: '' })).toBeNull();
    });

    it('leaves the three position fields empty without one, and the basis follows the QR', () => {
        const text = canonicalText({ ...base, position: null, qr: null });

        expect(text).toContain('|declared_interval||');
        expect(text).toContain('|||3|1||');
        expect(canonicalText(base)).toContain('|qr_window|');
    });
});

describe('positionOf', () => {
    it('gives the integers the phone signs and sends', () => {
        expect(
            positionOf({
                latitude: 19.16543,
                longitude: -99.1327,
                accuracy: 11.6,
            }),
        ).toEqual({ latE5: 1916543, lngE5: -9913270, accuracyM: 12 });
    });
});
