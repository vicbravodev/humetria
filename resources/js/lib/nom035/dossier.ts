import type { Nom035DossierRowData } from '@/types';

type Standing = {
    label: string;
    variant: 'success' | 'warning' | 'destructive' | 'secondary';
};

const STANDINGS: Record<Nom035DossierRowData['state'], Standing> = {
    done: { label: 'Lo tienes', variant: 'success' },
    partial: { label: 'En curso', variant: 'warning' },
    missing: { label: 'Falta', variant: 'destructive' },
    not_applicable: { label: 'No aplica', variant: 'secondary' },
};

/** What the badge of a dossier row says and how it looks: the words always name the state. */
export function dossierStanding(
    state: Nom035DossierRowData['state'],
): Standing {
    return STANDINGS[state];
}

/** The line under the title of the dossier: when it was issued, and that it shows only what the center has, when it does. */
export function dossierNote(issuedOn: string, readyOnly: boolean): string {
    return readyOnly
        ? `Emitido el ${issuedOn}. Solo incluye lo que el centro puede mostrar; lo que falta no aparece.`
        : `Emitido el ${issuedOn}. Incluye lo que el centro tiene y lo que falta, marcado como pendiente.`;
}
