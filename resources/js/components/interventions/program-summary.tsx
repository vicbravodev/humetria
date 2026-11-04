import ArcMeter from '@/components/humetria/arc-meter';
import RiskLevel from '@/components/humetria/risk-level';
import { meterText } from '@/lib/interventions';
import type { ProgramData } from '@/types';

/**
 * Where the Programa stands (pantalla Eval-Cerrada, the summary card): the highest level that led to it, how many actions
 * are done of those not cancelled and how many are overdue. It shows what is recorded; it never says the work center
 * complies.
 */
export default function ProgramSummary({ program }: { program: ProgramData }) {
    const { meter, highestLevel, overdue } = program;
    const share =
        meter.total === 0 ? 0 : Math.round((meter.done / meter.total) * 100);

    return (
        <section
            aria-label="Resumen del Programa"
            className="grid content-center gap-x-12 gap-y-7 card p-7 md:grid-cols-[minmax(0,1fr)_auto]"
        >
            <div className="flex flex-col justify-center gap-4">
                <span className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    Nivel más alto del resultado
                    {highestLevel !== null && (
                        <RiskLevel
                            level={highestLevel.value}
                            label={highestLevel.label}
                        />
                    )}
                </span>
                <p className="max-w-[520px] text-[22px] leading-8 font-medium tracking-[-0.01em] text-pretty">
                    <strong className="font-semibold">
                        {meterText(meter)}.
                    </strong>{' '}
                    {overdue > 0
                        ? `${overdue} ${overdue === 1 ? 'está vencida' : 'están vencidas'}: revisa su fecha o registra el avance.`
                        : 'Registra cada avance para llevar el control que pide el numeral 8.4.'}
                </p>
            </div>
            <div className="flex items-center justify-center md:border-l md:pl-7">
                <ArcMeter
                    value={meter.done}
                    max={meter.total}
                    label={meterText(meter)}
                    size="lg"
                >
                    <span className="text-[40px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {share}&nbsp;%
                    </span>
                    <span className="max-w-[100px] text-caption leading-4 text-muted-foreground">
                        de las acciones hechas
                    </span>
                </ArcMeter>
            </div>
        </section>
    );
}
