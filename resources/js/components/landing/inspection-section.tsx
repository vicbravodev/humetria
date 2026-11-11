import InspectionLine from '@/components/landing/inspection-line';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import Reveal from '@/components/landing/reveal';
import StepBar from '@/components/landing/step-bar';
import { cn } from '@/lib/utils';

const items = [
    {
        text: 'Política de prevención con acuses de lectura',
        tool: 'NOM-035',
        done: true,
    },
    {
        text: 'Evaluación de puestos de pie y plan de pausas',
        tool: 'Ley Silla',
        done: true,
    },
    {
        text: 'Bitácora de cada queja, con sus plazos',
        tool: 'Buzón de quejas',
        done: true,
    },
    {
        text: 'Reporte de jornada para la autoridad',
        tool: 'Registro de jornada',
        done: true,
    },
    {
        text: 'Constancia anual de vacaciones',
        tool: 'Vacaciones y permisos',
        done: true,
    },
    {
        text: 'Informe del numeral 7.7 · faltan 31 respuestas',
        tool: 'NOM-035',
        done: false,
    },
    {
        text: 'Listas de verificación validadas · faltan 3',
        tool: 'NOM-037',
        done: false,
    },
];

const ready = items.filter((item) => item.done).length;

/** The moment the whole page leads to: an inspector asks, and the file is already there, with what it still lacks. */
export default function InspectionSection() {
    return (
        <section
            id="inspeccion"
            data-test="inspection"
            aria-labelledby="inspeccion-title"
            className={SECTION}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-8 sm:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:items-start lg:gap-14',
                )}
            >
                <Reveal className="flex flex-col gap-5 lg:sticky lg:top-28">
                    <h2 id="inspeccion-title" className={TITLE}>
                        Si mañana llega un inspector, ya sabes qué enseñarle
                    </h2>
                    <p className={LEAD}>
                        Humetría arma el expediente de cada centro mientras
                        trabajas y te dice qué falta antes de que alguien lo
                        pregunte.
                    </p>
                    <p className="max-w-[52ch] border-l-2 pl-3.5 text-meta text-muted-foreground">
                        Humetría ordena la evidencia para que esté a la mano. No
                        certifica el cumplimiento de ninguna norma.
                    </p>
                </Reveal>
                <div className="overflow-hidden rounded-3xl border bg-card shadow-raised">
                    <div className="grid gap-3.5 border-b p-5">
                        <p className="flex items-center justify-between gap-3">
                            <b className="text-[17px] font-semibold">
                                Expediente · Planta Querétaro
                            </b>
                            <span className="text-meta text-muted-foreground tabular-nums">
                                <b className="font-semibold text-foreground">
                                    {ready}
                                </b>{' '}
                                de {items.length}
                            </span>
                        </p>
                        <StepBar value={ready / items.length} delay={0.2} />
                    </div>
                    <ul>
                        {items.map((item, index) => (
                            <InspectionLine
                                key={item.text}
                                {...item}
                                index={index}
                            />
                        ))}
                    </ul>
                    <p className="border-t bg-sidebar px-5 py-3.5 text-caption text-muted-foreground">
                        Datos de ejemplo
                    </p>
                </div>
            </div>
        </section>
    );
}
