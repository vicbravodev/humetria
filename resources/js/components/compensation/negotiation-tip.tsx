import { Info } from 'lucide-react';

/** «Para la mesa de negociación» (board V4-Comp-Inicio): about what a point to each group costs a year, and a month of retroactivity. */
export default function NegotiationTip({
    unionized,
    trusted,
}: {
    unionized: string;
    trusted: string;
}) {
    return (
        <aside
            aria-labelledby="negotiation-tip"
            className="flex gap-3.5 rounded-xl bg-accent px-6 py-5 text-accent-foreground"
        >
            <Info aria-hidden className="mt-0.5 size-5 shrink-0" />
            <div className="grid gap-1">
                <h2 id="negotiation-tip" className="font-semibold">
                    Para la mesa de negociación
                </h2>
                <p className="text-sm">
                    Cada punto de aumento a sindicalizados cuesta cerca de{' '}
                    <strong className="font-semibold">
                        {unionized} al año
                    </strong>
                    {trusted === null ? (
                        '.'
                    ) : (
                        <>
                            ; a confianza, cerca de{' '}
                            <strong className="font-semibold">{trusted}</strong>
                            .
                        </>
                    )}{' '}
                    Cada mes de retroactivo suma una doceava parte del costo
                    anual.
                </p>
            </div>
        </aside>
    );
}
