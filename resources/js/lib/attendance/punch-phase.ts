/** Where a mark is while the person waits: reading the position, asking their fingerprint, face or PIN, or the server checking. */
export type PunchPhase = 'locating' | 'signing' | 'verifying';

export type PhaseRow = { label: string; state: 'done' | 'current' | 'pending' };

const LABELS: Record<PunchPhase, string> = {
    locating: 'Leyendo su ubicación',
    signing: 'Confirme con su huella, su rostro o su PIN',
    verifying: 'Comprobando el código y la zona',
};

const ORDER: readonly PunchPhase[] = ['locating', 'signing', 'verifying'];

/** The three tests in the order they happen, each done, in course or pending: so the person sees every one pass. */
export function phaseRows(phase: PunchPhase): PhaseRow[] {
    const at = ORDER.indexOf(phase);

    return ORDER.map((step, index) => ({
        label: LABELS[step],
        state: index < at ? 'done' : index === at ? 'current' : 'pending',
    }));
}
