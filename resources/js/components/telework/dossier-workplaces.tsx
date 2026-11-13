import DossierMissing from '@/components/telework/dossier-missing';
import DossierTable from '@/components/telework/dossier-table';
import DossierWorkplaceRow from '@/components/telework/dossier-workplace-row';
import type { DossierWorkplace } from '@/types';

/** 5.1.1 and 5.5: each workplace with the date, decision and method of the Comisión's latest review (no notes). */
export default function DossierWorkplaces({
    workplaces,
}: {
    workplaces: DossierWorkplace[];
}) {
    if (workplaces.length === 0) {
        return (
            <DossierMissing>
                no hay lugares de trabajo registrados.
            </DossierMissing>
        );
    }

    return (
        <DossierTable
            columns={[
                'Persona',
                'Situación',
                'Última revisión',
                'Decisión',
                'Método',
                'Vigente hasta',
                'Respuestas desfavorables',
                'Nota de la Comisión',
            ]}
        >
            {workplaces.map((place, index) => (
                <DossierWorkplaceRow
                    key={`${place.person}-${index}`}
                    place={place}
                />
            ))}
        </DossierTable>
    );
}
