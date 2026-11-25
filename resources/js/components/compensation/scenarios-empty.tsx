import { Archive, GitCompareArrows } from 'lucide-react';
import NewScenarioButton from '@/components/compensation/new-scenario-button';
import EmptyState from '@/components/humetria/empty-state';
import { useIsQuick } from '@/hooks/use-scenario-routes';

/** When there is no scenario in the tab: the next step is to make the first one, or to archive one. */
export default function ScenariosEmpty({ archived }: { archived: boolean }) {
    const quick = useIsQuick();

    if (archived) {
        return (
            <EmptyState icon={Archive} title="Nada archivado">
                Un escenario que ya no uses se archiva y aquí lo encuentras. Un
                escenario nunca se borra.
            </EmptyState>
        );
    }

    if (quick) {
        return (
            <EmptyState
                icon={GitCompareArrows}
                title="Todavía no hay cotizaciones"
                action={<NewScenarioButton />}
            >
                Una cotización parte de cuántas personas son y cuánto ganan en
                promedio: ves lo que cuesta cada aumento, con aguinaldo, prima
                vacacional y cuotas patronales. Cuando quieras ir de sueldo en
                sueldo, pasa a Compensación avanzada.
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
