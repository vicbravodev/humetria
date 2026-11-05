import type { ExternalAlternative } from '@/types';

/** The channel is paused (board V4-Quejas-Seguimiento): no new complaints for now, the ones in course still open, and the other way. */
export default function FollowUpPaused({
    alternative,
}: {
    alternative: ExternalAlternative;
}) {
    return (
        <section
            aria-labelledby="paused"
            className="flex flex-col gap-2 rounded-lg bg-warning px-4 py-3 text-warning-foreground"
        >
            <h2 id="paused" className="font-semibold">
                El canal está en pausa
            </h2>
            <p className="text-base">
                La empresa pausó por un momento la recepción de quejas nuevas.
                Si ya presentó una, puede seguir viéndola con su referencia.
            </p>
            <p className="text-base">
                Puede acudir también a la {alternative.name}. {alternative.note}{' '}
                {alternative.email} · {alternative.phone}
            </p>
        </section>
    );
}
