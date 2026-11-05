import Notice from '@/components/humetria/notice';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

/** What the reader must know before trusting the numbers: what is out of date, what could not be calculated, who is costed apart. */
export default function ReportWarnings({ report }: { report: ScenarioReport }) {
    const { warnings } = report;
    const { individual } = warnings;

    return (
        <div className="grid gap-3">
            {warnings.stale.length > 0 && (
                <Notice>
                    <strong>Hay valores fiscales sin actualizar.</strong> La
                    plataforma aún no tiene los de algunos años y usa el último
                    que conoce: {warnings.stale.join(', ')}.
                </Notice>
            )}
            {warnings.uncalculated.length > 0 && (
                <Notice>
                    <strong>
                        No se calculó: {warnings.uncalculated.join(', ')}.
                    </strong>{' '}
                    Falta la prima de riesgo de trabajo o el estado de las
                    personas; captúralos y el costo sube.
                </Notice>
            )}
            {warnings.uncosted.length > 0 && (
                <Notice>
                    <strong>
                        Falta el salario de una categoría en el tabulador:
                    </strong>{' '}
                    {warnings.uncosted
                        .map(
                            (grade) =>
                                `${grade.label} (${countOf(grade.people, 'persona', 'personas')})`,
                        )
                        .join(', ')}
                    . Esas personas no están en el costo.
                </Notice>
            )}
            {individual.count > 0 && (
                <Notice>
                    <strong>
                        {countOf(
                            individual.count,
                            'persona tiene',
                            'personas tienen',
                        )}{' '}
                        sueldo individual ({individual.percent} % de la
                        plantilla costeada).
                    </strong>{' '}
                    Se calculan con su sueldo y no con el de su categoría.
                    {individual.over_tenth &&
                        ' Son más de la décima parte: es probable que el tabulador no describa la realidad.'}
                </Notice>
            )}
        </div>
    );
}
