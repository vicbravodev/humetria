import LeftOutNotice from '@/components/compensation/left-out-notice';
import ScenarioCompareBars from '@/components/compensation/scenario-compare-bars';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useIsUnion } from '@/hooks/use-is-union';
import type {
    OtherScenario,
    ScenarioPreview,
} from '@/lib/compensation/scenario-draft';
import { compareBars, headlineTexts } from '@/lib/compensation/scenario-draft';

/**
 * The live cost (board V4-Comp-Escenario): the first year with its retroactivity, each year after and per person a month,
 * against the other scenarios of this account, who is left out and «Guardar y ver reporte». Nothing is saved before that.
 */
export default function ScenarioCostAside({
    preview,
    loading,
    others,
    processing,
}: {
    preview: ScenarioPreview | null;
    loading: boolean;
    others: OtherScenario[];
    processing: boolean;
}) {
    const today = useIsUnion()
        ? 'lo que hoy paga la empresa'
        : 'lo que pagas hoy';
    const texts = headlineTexts(preview);
    const bars =
        preview === null
            ? []
            : compareBars(others, {
                  cents: preview.first_year_cents,
                  text: preview.first_year_text,
              });

    return (
        <aside
            aria-label="Costo del escenario"
            aria-busy={loading}
            className="grid content-start gap-5 card p-6 shadow-raised min-[1240px]:sticky min-[1240px]:top-6"
        >
            <div className="grid gap-1.5" aria-live="polite">
                <p className="text-muted-foreground">Costo del primer año</p>
                <p className="text-[40px] leading-[48px] font-semibold tracking-[-0.02em] tabular-nums">
                    {texts.first}
                </p>
                {preview !== null && (
                    <p className="text-sm text-muted-foreground">
                        Incluye {preview.retroactive_text} de retroactivo · +
                        {preview.over_today_text} % sobre {today}
                    </p>
                )}
            </div>
            <dl className="grid gap-3 border-y py-4">
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        Al año, al arranque
                    </dt>
                    <dd className="font-semibold tabular-nums">
                        {texts.recurring}
                    </dd>
                </div>
                <div className="flex justify-between gap-3">
                    <dt className="text-muted-foreground">
                        Por persona al mes
                    </dt>
                    <dd className="font-semibold tabular-nums">
                        {texts.perPerson}
                    </dd>
                </div>
            </dl>
            {bars.length > 0 && <ScenarioCompareBars bars={bars} />}
            {preview !== null && preview.left_out > 0 && (
                <LeftOutNotice people={preview.left_out} />
            )}
            <Button
                type="submit"
                size="lg"
                disabled={processing}
                className="w-full"
            >
                {processing && <Spinner />}
                Guardar y ver reporte
            </Button>
        </aside>
    );
}
