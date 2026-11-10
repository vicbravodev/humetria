import ProgressBar from '@/components/humetria/progress-bar';

/**
 * How many of the company answered and how the company stands, as a figure of the whole company: never a group, since
 * the person reads what will be done, not what a group said.
 */
export default function PortalParticipation({
    percent,
    index,
}: {
    percent: number;
    index: number | null;
}) {
    return (
        <section
            aria-label="Participación"
            className="grid gap-4 card p-5 sm:grid-cols-2"
        >
            <div className="grid gap-2">
                <p className="text-base text-muted-foreground">
                    Personas que contestaron
                </p>
                <p className="text-[26px] leading-[34px] font-semibold tabular-nums">
                    {percent} %
                </p>
                <ProgressBar
                    value={percent}
                    max={100}
                    label="Participación en la encuesta"
                />
            </div>
            {index !== null && (
                <div className="grid gap-2">
                    <p className="text-base text-muted-foreground">
                        Compromiso con la empresa
                    </p>
                    <p className="text-[26px] leading-[34px] font-semibold tabular-nums">
                        {index} %
                    </p>
                    <p className="text-base text-muted-foreground">
                        De respuestas favorables en las preguntas de compromiso.
                    </p>
                </div>
            )}
        </section>
    );
}
