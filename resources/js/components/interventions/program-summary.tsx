import ArcMeter from '@/components/humetria/arc-meter';
import RiskLevel from '@/components/humetria/risk-level';
import { coverageText, meterText } from '@/lib/interventions';
import type { ProgramData } from '@/types';

/**
 * Where the Programa stands (pantalla Eval-Cerrada, the summary card): the highest level that led to it, how many actions
 * are done of those not cancelled, how many themes have actions and how many are overdue. The arc is the server's share of
 * the work: it never reads 100 % while a theme has no action or a follow-up evaluation waits. It shows what is recorded; it
 * never says the work center complies.
 */
export default function ProgramSummary({ program }: { program: ProgramData }) {
    const { meter, coverage, percent, highestLevel, overdue } = program;

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
                        {meterText(meter)}
                    </strong>{' '}
                    · {coverageText(coverage)}.{' '}
                    {overdue > 0
                        ? `${overdue} ${overdue === 1 ? 'está vencida' : 'están vencidas'}: revisa su fecha o registra el avance.`
                        : 'Registra cada avance para llevar el control que pide el numeral 8.4.'}
                </p>
            </div>
            <div className="flex items-center justify-center md:border-l md:pl-7">
                <ArcMeter
                    value={percent}
                    max={100}
                    label={`${percent} % de avance del Programa`}
                    size="lg"
                >
                    <span className="text-[40px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {percent}&nbsp;%
                    </span>
                    <span className="max-w-[100px] text-caption leading-4 text-muted-foreground">
                        de avance del Programa
                    </span>
                </ArcMeter>
            </div>
        </section>
    );
}
