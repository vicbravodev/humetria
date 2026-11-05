import { Check } from 'lucide-react';
import { themesLine } from '@/lib/share-results';
import type { ShareResultsProps } from '@/types';

/**
 * «Qué incluye» (board V2-N035-Compartir): what the summary always carries, and the measures of the Programa, which RH
 * chooses to show.
 */
export default function ShareIncludes({
    props,
    withMeasures,
    onMeasures,
}: {
    props: ShareResultsProps;
    withMeasures: boolean;
    onMeasures: (shown: boolean) => void;
}) {
    const rows = [
        {
            title: 'Cuántas personas contestaron',
            detail:
                props.answered === null
                    ? `Menos de ${props.minimum}`
                    : `${props.answered}`,
        },
        {
            title: 'Los temas que más atención necesitan',
            detail:
                themesLine(props.themes) ??
                'Ninguno, o contestaron muy pocas personas para decirlo',
        },
        {
            title: 'Cómo avisar de violencia o un trato injusto',
            detail:
                props.channel === null
                    ? 'Tu canal de quejas no está abierto'
                    : 'Sale de tu canal de quejas',
        },
    ];

    return (
        <ul className="@container overflow-hidden card">
            {rows.map((row) => (
                <li
                    key={row.title}
                    className="grid grid-cols-[24px_minmax(0,1fr)_auto] items-center gap-3.5 border-t px-[18px] py-3.5 first:border-t-0"
                >
                    <span
                        aria-hidden
                        className="inline-flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <Check className="size-4" />
                    </span>
                    <span className="min-w-0">
                        <span className="block font-medium">{row.title}</span>
                        <span className="block text-sm text-muted-foreground">
                            {row.detail}
                        </span>
                    </span>
                    <span className="text-sm text-muted-foreground">
                        Siempre
                    </span>
                </li>
            ))}
            <li className="border-t">
                <label className="grid min-h-[64px] cursor-pointer grid-cols-[24px_minmax(0,1fr)] items-center gap-3.5 px-[18px] py-3.5 hover:bg-muted">
                    <input
                        type="checkbox"
                        name="show_measures"
                        value="1"
                        checked={withMeasures}
                        onChange={(event) => onMeasures(event.target.checked)}
                        className="size-5 accent-primary"
                    />
                    <span className="min-w-0">
                        <span className="block font-medium">
                            Lo que hará la empresa
                        </span>
                        <span className="block text-sm text-muted-foreground">
                            {props.measures.length === 0
                                ? 'Tu programa de intervención aún no tiene acciones para todo el centro'
                                : `${props.measures.length === 1 ? 'La acción' : `Las ${props.measures.length} acciones`} de tu programa de intervención para todo el centro`}
                        </span>
                    </span>
                </label>
            </li>
        </ul>
    );
}
