import InspectionLine from '@/components/landing/inspection-line';
import Reveal from '@/components/landing/reveal';

const items = [
    { text: 'Política de prevención con acuses de lectura', tool: 'NOM-035' },
    {
        text: 'Informe del numeral 7.7 y programa de intervención',
        tool: 'NOM-035',
    },
    {
        text: 'Listas de verificación validadas por la Comisión',
        tool: 'NOM-037',
    },
    {
        text: 'Evaluación de puestos de pie y plan de pausas',
        tool: 'Ley Silla',
    },
    { text: 'Bitácora de cada queja, con sus plazos', tool: 'Buzón de quejas' },
    {
        text: 'Reporte de jornada para la autoridad',
        tool: 'Registro de jornada',
    },
] as const;

/** The moment the whole page leads to: an inspector asks, and the file is already there. */
export default function InspectionSection() {
    return (
        <section
            data-test="inspection"
            aria-labelledby="inspeccion-title"
            className="bg-primary text-primary-foreground dark:bg-accent dark:text-accent-foreground"
        >
            <div className="mx-auto grid max-w-[1240px] gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-20 lg:py-28">
                <Reveal className="flex flex-col gap-5">
                    <h2
                        id="inspeccion-title"
                        className="text-[34px] leading-[1.08] font-semibold tracking-[-0.03em] text-balance sm:text-[52px]"
                    >
                        Si mañana llega un inspector, ya sabes qué enseñarle
                    </h2>
                    <p className="max-w-[44ch] text-lg leading-7">
                        Humetría arma el expediente de cada centro mientras
                        trabajas y te dice qué falta antes de que alguien lo
                        pregunte.
                    </p>
                    <p className="max-w-[44ch] text-sm leading-5">
                        Humetría ordena la evidencia para que RH la presente. No
                        certifica el cumplimiento de ninguna norma.
                    </p>
                </Reveal>
                <ul className="flex flex-col self-center">
                    {items.map((item, index) => (
                        <InspectionLine
                            key={item.text}
                            text={item.text}
                            tool={item.tool}
                            index={index}
                        />
                    ))}
                </ul>
            </div>
        </section>
    );
}
