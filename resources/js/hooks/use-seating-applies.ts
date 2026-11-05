import { useState } from 'react';
import { useSave } from '@/hooks/use-save';
import type { SectorValue } from '@/lib/applicability';
import { update } from '@/routes/tenant/work-centers/applicability';

export type SeatingDeclaration = {
    standing: boolean;
    sector: SectorValue | null;
    declaredOn: string;
};

/**
 * The Ley Silla answers of «¿Te aplica?» (board V2-Aplica-Otras): whether someone works standing and the sector, as
 * declared or as being changed, and keeping them on the server for one center.
 */
export function useSeatingApplies(
    tenantSlug: string,
    workCenterId: string,
    saved: SeatingDeclaration | null,
) {
    const [standing, setStanding] = useState(saved?.standing ?? null);
    const [sector, setSector] = useState(saved?.sector ?? null);
    const saving = useSave();
    const changed =
        saved === null ||
        standing !== saved.standing ||
        (standing === true && sector !== saved.sector);

    return {
        standing,
        sector,
        changed,
        saving,
        answerStanding: (value: boolean) => setStanding(value),
        answerSector: (value: SectorValue) => setSector(value),
        save: () =>
            saving.save(update.url(tenantSlug), {
                centers: [
                    {
                        id: workCenterId,
                        standing,
                        sector: standing === true ? sector : null,
                    },
                ],
            }),
    };
}
