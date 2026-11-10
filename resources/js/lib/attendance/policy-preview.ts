import type { AttendancePolicy } from '@/types';

/**
 * «Así queda una marca» (board A6): what each way of marking does under the policy being edited, read as the server reads
 * it (MarkPolicyCheck, criterios 4.2 to 4.4 and 5.3). tests/Fixtures/attendance/mark-outcomes.json holds the table that
 * both this file's test and the PHP test of MarkPolicyCheck check, so the two cannot drift apart.
 */

/** How much the mark proves (EvidenceLevel), or that it is not taken at all. */
export type MarkLevel = 'strong' | 'medium' | 'basic' | 'rejected';

export type MarkOutcome = { level: MarkLevel; label: string; note: string };

export type CaseId =
    | 'qr_and_inside'
    | 'inside_no_qr'
    | 'qr_inaccurate'
    | 'qr_outside'
    | 'trusted_browser';

export type MarkCase = { id: CaseId; text: string; outcome: MarkOutcome };

const LABELS: Record<MarkLevel, string> = {
    strong: 'Fuerte',
    medium: 'Media',
    basic: 'Básica',
    rejected: 'Se rechaza',
};

const outcome = (level: MarkLevel, note: string): MarkOutcome => ({
    level,
    label: LABELS[level],
    note,
});

/** Inside the zone without the QR: refused where the QR is asked; otherwise the location alone proves it. */
function insideWithoutQr(policy: AttendancePolicy): MarkOutcome {
    return policy.requireQrOnsite
        ? outcome('rejected', 'Pide escanear la pantalla')
        : outcome('medium', 'Solo la ubicación');
}

/** A reading too loose to tell inside from outside: refused when blocking; otherwise kept without a zone, flagged. */
function inaccurate(policy: AttendancePolicy): MarkOutcome {
    return policy.outOfZone === 'block'
        ? outcome('rejected', 'No se sabe si está dentro de la zona')
        : outcome(
              'medium',
              'La ubicación no cuenta: se guarda sin zona, señalada',
          );
}

function outside(policy: AttendancePolicy): MarkOutcome {
    return policy.outOfZone === 'block'
        ? outcome('rejected', 'No se guarda la marca')
        : outcome('medium', 'Se guarda sin zona, señalada');
}

/** Trusted staff may skip the QR and the zone from their browser; with it off they mark like everyone else. */
function trustedBrowser(policy: AttendancePolicy): MarkOutcome {
    if (policy.trustedMayUseBrowser) {
        return outcome('basic', 'Sin ubicación guardada');
    }

    return policy.requireQrOnsite
        ? outcome(
              'rejected',
              'Apagado: pide escanear la pantalla como el resto',
          )
        : outcome('medium', 'Apagado: marca como el resto, con su ubicación');
}

/** The five cases of a person on site at a center with zones, under `policy`. */
export function markCases(policy: AttendancePolicy): MarkCase[] {
    return [
        {
            id: 'qr_and_inside',
            text: 'En el centro, con QR y ubicación dentro de la zona',
            outcome: outcome('strong', 'QR y ubicación'),
        },
        {
            id: 'inside_no_qr',
            text: 'En el centro, sin escanear el QR',
            outcome: insideWithoutQr(policy),
        },
        {
            id: 'qr_inaccurate',
            text: `Con QR, pero con ubicación peor que ${policy.maxAccuracyM} m`,
            outcome: inaccurate(policy),
        },
        {
            id: 'qr_outside',
            text: 'Con QR, pero fuera de todas las zonas',
            outcome: outside(policy),
        },
        {
            id: 'trusted_browser',
            text: 'Personal de confianza, desde su navegador',
            outcome: trustedBrowser(policy),
        },
    ];
}

const SETTINGS = [
    'outOfZone',
    'maxAccuracyM',
    'requireQrOnsite',
    'trustedMayUseBrowser',
    'retentionMonths',
] as const;

/** How many of the five settings differ from the saved ones. */
export function policyChanges(
    saved: AttendancePolicy,
    draft: AttendancePolicy,
): number {
    return SETTINGS.filter((key) => saved[key] !== draft[key]).length;
}

/** The save bar's words: nothing to save, or how many changes are waiting and that they go to the bitácora. */
export function changesText(changes: number): string {
    if (changes === 0) {
        return 'Sin cambios por guardar';
    }

    return changes === 1
        ? '1 cambio sin guardar · quedará en la bitácora'
        : `${changes} cambios sin guardar · quedarán en la bitácora`;
}

/** What the precision chosen means: above the default 100 m a weak reading may pass for being inside a zone. */
export function precisionNote(meters: number): string {
    return meters > 100
        ? `Con ${meters} m, una ubicación con señal débil puede caer dentro de una zona sin que la persona esté ahí. Considera bajarla.`
        : `Con ${meters} m, solo cuentan las ubicaciones claras. Quien marque con mala señal verá un aviso y podrá pedir una corrección.`;
}
