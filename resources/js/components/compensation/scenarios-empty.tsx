import { Archive, GitCompareArrows } from 'lucide-react';
import NewScenarioButton from '@/components/compensation/new-scenario-button';
import EmptyState from '@/components/humetria/empty-state';

/** When there is no scenario in the tab: the next step is to make the first one, or to archive one. */
export default function ScenariosEmpty({ archived }: { archived: boolean }) {
    if (archived) {
        return (
            <EmptyState icon={Archive} title="Nada archivado">
                Un escenario que ya no uses se archiva y aquí lo encuentras. Un
                escenario nunca se borra.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={GitCompareArrows}
            title="Todavía no hay escenarios"
            action={<NewScenarioButton />}
        >
            Un escenario es una propuesta de revisión: cuánto subir el salario y
            qué prestaciones cambiar. Armas uno y ves lo que cuesta, a un año y
            a varios.
        </EmptyState>
    );
}
