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

const done = items.filter((item) => item.done);
const missing = items.filter((item) => !item.done);

/**
 * The moment the whole page leads to: an inspector asks, and the file is already there, with what it still lacks. The
 * ready pieces and the missing ones sit in two columns, so what is left reads at a glance.
 */
export default function InspectionSection() {
    return (
        <section
            id="inspeccion"
            data-test="inspection"
            aria-labelledby="inspeccion-title"
            className={SECTION}
        >
            <div className={cn(WRAP, 'flex flex-col gap-8 lg:gap-12')}>
                <Reveal className="flex max-w-[820px] flex-col gap-5">
                    <h2 id="inspeccion-title" className={TITLE}>
                        Si mañana llega un inspector, ya sabes qué enseñarle
                    </h2>
                    <p className={LEAD}>
                        Humetría va juntando los documentos de cada centro y te
                        avisa qué falta antes de que alguien lo pida.
                    </p>
                    <p className="max-w-[52ch] text-meta text-muted-foreground">
                        Te ayudamos a reunir tus comprobantes. No sustituimos a
                        la STPS ni certificamos que cumples con una norma.
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
                                    {done.length}
                                </b>{' '}
                                de {items.length}
                            </span>
                        </p>
                        <StepBar
                            value={done.length / items.length}
                            delay={0.2}
                        />
                    </div>
                    <div className="grid lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] lg:divide-x">
                        <ul>
                            {done.map((item, index) => (
                                <InspectionLine
                                    key={item.text}
                                    {...item}
                                    index={index}
                                />
                            ))}
                        </ul>
                        <ul className="border-t lg:border-t-0">
                            {missing.map((item, index) => (
                                <InspectionLine
                                    key={item.text}
                                    {...item}
                                    index={done.length + index}
                                />
                            ))}
                        </ul>
                    </div>
                    <p className="border-t bg-sidebar px-5 py-3.5 text-caption text-muted-foreground">
                        Datos de ejemplo
                    </p>
                </div>
            </div>
        </section>
    );
}
