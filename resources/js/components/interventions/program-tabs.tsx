import CountTabs from '@/components/humetria/count-tabs';
import { useProgramTab } from '@/hooks/use-program-tab';
import { tabHref } from '@/lib/interventions';

/** The three views of the Programa with how many each holds (pantalla Empleados, tabs with a count). */
export default function ProgramTabs({
    path,
    counts,
}: {
    /** The address of the Programa without a query. */
    path: string;
    counts: { rows: number; actions: number; followUp: number };
}) {
    const active = useProgramTab();

    return (
        <CountTabs
            label="Vistas del Programa"
            tabs={[
                {
                    label: 'Filas',
                    count: counts.rows,
                    href: tabHref(path, 'rows'),
                    active: active === 'rows',
                },
                {
                    label: 'Acciones',
                    count: counts.actions,
                    href: tabHref(path, 'actions'),
                    active: active === 'actions',
                },
                {
                    label: 'Evaluación posterior',
                    count: counts.followUp,
                    href: tabHref(path, 'follow-up'),
                    active: active === 'follow-up',
                },
            ]}
        />
    );
}
