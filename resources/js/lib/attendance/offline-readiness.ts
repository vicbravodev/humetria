import { OPEN_ONLINE } from '@/lib/attendance/offline-text';

/*
 * Whether a phone can mark without a connection, row by row, for «Listo para marcar sin internet». The company's switch, the
 * agreement and the tickets block; the camera, the location and who signs only warn (the server needs no QR and no position,
 * and the practice of the sign-up is a guide). How old the tickets are, the server decides.
 */

export type ReadinessFix =
    | 'open-online'
    | 'accept-agreement'
    | 'camera'
    | 'location'
    | 'prepare-phone';

export type ReadinessRow = {
    key: 'company' | 'agreement' | 'tickets' | 'camera' | 'location' | 'signer';
    ok: boolean;
    blocking: boolean;
    text: string;
    fix: ReadinessFix | null;
};

export type ReadinessInput = {
    context: { offline: boolean; agreementVersion: number | null } | null;
    tickets: number;
    camera: PermissionState | 'unknown';
    location: PermissionState | 'unknown';
    practice: 'passkey' | 'failed' | null;
    /** The phone's own key has a pass that is still good. */
    passValid: boolean;
    /** A remote person reads no code, so the camera does not matter to them. */
    remote: boolean;
};

function companyRow(context: ReadinessInput['context']): ReadinessRow {
    if (context === null) {
        return {
            key: 'company',
            ok: false,
            blocking: true,
            text: OPEN_ONLINE,
            fix: 'open-online',
        };
    }

    return {
        key: 'company',
        ok: context.offline,
        blocking: true,
        text: context.offline
            ? 'Su empresa activó marcar sin internet'
            : 'Su empresa no tiene activado marcar sin internet',
        fix: null,
    };
}

function agreementRow(context: ReadinessInput['context']): ReadinessRow {
    const ok = context?.agreementVersion != null;

    return {
        key: 'agreement',
        ok,
        blocking: true,
        text: ok
            ? 'Ya aceptó el acuerdo de registro'
            : 'Falta aceptar el acuerdo de registro: se acepta con su próxima marca con internet',
        fix: ok ? null : 'accept-agreement',
    };
}

function ticketsRow(tickets: number): ReadinessRow {
    const ok = tickets > 0;

    return {
        key: 'tickets',
        ok,
        blocking: true,
        text: ok
            ? 'Tiene permisos para marcar sin internet'
            : 'Aún no tiene permisos para marcar sin internet: abra Humetría con internet',
        fix: ok ? null : 'open-online',
    };
}

function permissionRow(
    key: 'camera' | 'location',
    state: PermissionState | 'unknown',
): ReadinessRow {
    const ok = state === 'granted';
    const what = key === 'camera' ? 'la cámara' : 'la ubicación';
    const text = {
        granted: `Ya permitió ${what}`,
        denied: `No permitió ${what}: puede marcar sin ella, pero Recursos Humanos lo verá`,
        prompt: `Aún no permitió ${what}: puede marcar sin ella, pero Recursos Humanos lo verá`,
        unknown: `Puede permitir ${what} para que su marca lleve más respaldo`,
    }[state];

    return { key, ok, blocking: false, text, fix: ok ? null : key };
}

function signerRow({
    practice,
    passValid,
}: Pick<ReadinessInput, 'practice' | 'passValid'>): ReadinessRow {
    const ok = practice !== 'failed' || passValid;

    return {
        key: 'signer',
        ok,
        blocking: false,
        text: ok
            ? 'Su llave de acceso o este teléfono firmarán sus marcas'
            : 'Su llave no firmó sin internet en la práctica: prepare este teléfono',
        fix: ok ? null : 'prepare-phone',
    };
}

export function offlineReadiness(input: ReadinessInput): {
    ready: boolean;
    fewTickets: boolean;
    rows: ReadinessRow[];
} {
    const { context, tickets } = input;
    const rows = [
        companyRow(context),
        agreementRow(context),
        ticketsRow(tickets),
        ...(input.remote ? [] : [permissionRow('camera', input.camera)]),
        permissionRow('location', input.location),
        signerRow(input),
    ];

    return {
        ready: rows.filter((row) => row.blocking).every((row) => row.ok),
        fewTickets: tickets >= 1 && tickets <= 2,
        rows,
    };
}
