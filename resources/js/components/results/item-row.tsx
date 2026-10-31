import ShareMeter from '@/components/humetria/share-meter';
import { unfavorableText } from '@/lib/results';
import type { ItemResult } from '@/types';

/** One question: its words, how large the unfavorable part of its answers is, and the count behind it. */
export default function ItemRow({ item }: { item: ItemResult }) {
    const label = `${item.number}. ${item.text}`;

    return (
        <li className="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_10rem_13rem] sm:items-center sm:gap-5">
            <p className="leading-5">
                <span className="text-muted-foreground tabular-nums">
                    {item.number}.
                </span>{' '}
                {item.text}
            </p>
            {item.unfavorable === null ? (
                <p className="text-sm text-muted-foreground sm:col-span-2 sm:text-right">
                    Menos de 5 respuestas: no se muestra
                </p>
            ) : (
                <>
                    <ShareMeter
                        value={item.unfavorable}
                        max={item.answered}
                        label={`Respuestas desfavorables a ${label}`}
                    />
                    <p className="text-sm text-muted-foreground tabular-nums sm:text-right">
                        {unfavorableText(item.unfavorable, item.answered)}
                    </p>
                </>
            )}
        </li>
    );
}
