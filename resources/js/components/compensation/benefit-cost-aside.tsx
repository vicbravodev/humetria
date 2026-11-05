import type { BenefitPreview } from '@/lib/compensation/benefit-draft';
import { reachText } from '@/lib/compensation/benefit-draft';
import { countOf } from '@/lib/plural';

/** «Hoy cuesta» (board V4-Comp-Prestacion): what the benefit costs a year today with its charges, and per person a month. */
export default function BenefitCostAside({
    preview,
    loading,
    group,
    scenarios,
}: {
    preview: BenefitPreview | null;
    loading: boolean;
    group: string;
    scenarios: number;
}) {
    return (
        <aside
            aria-label="Lo que cuesta hoy"
            aria-busy={loading}
            className="grid content-start gap-4 card p-6 min-[1240px]:sticky min-[1240px]:top-6"
        >
            <div className="grid gap-1" aria-live="polite">
                <p className="text-muted-foreground">Hoy cuesta</p>
                <p className="text-[40px] leading-[48px] font-semibold tracking-[-0.02em] tabular-nums">
                    {preview?.yearly_text ?? '—'}
                </p>
                {preview !== null && (
                    <p className="text-sm text-muted-foreground">
                        {reachText(preview.people, group)}
                    </p>
                )}
            </div>
            <dl className="border-t pt-4">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        Por persona al mes
                    </dt>
                    <dd className="font-semibold tabular-nums">
                        {preview?.per_person_month_text ?? '—'}
                    </dd>
                </div>
            </dl>
            {scenarios > 0 && (
                <p className="rounded-xl bg-accent px-4 py-3 text-sm text-accent-foreground">
                    Al guardar,{' '}
                    {scenarios === 1
                        ? 'tu escenario se recalcula solo'
                        : `tus ${countOf(scenarios, 'escenario', 'escenarios')} se recalculan solos`}
                    : cada uno lee el contrato como está.
                </p>
            )}
        </aside>
    );
}
