import Initials from '@/components/humetria/initials';
import { answeredLine, themesLine } from '@/lib/share-results';
import type { ShareResultsProps } from '@/types';

/** «Así lo ve cada persona» (board V2-N035-Compartir): the summary as a phone shows it, before it is published. */
export default function SharePreview({
    props,
    withMeasures,
}: {
    props: ShareResultsProps;
    withMeasures: boolean;
}) {
    const themes = themesLine(props.themes);

    return (
        <aside aria-labelledby="share-preview" className="flex flex-col gap-3">
            <h2
                id="share-preview"
                className="text-center text-sm text-muted-foreground"
            >
                Así lo ve cada persona
            </h2>
            <div className="overflow-hidden rounded-[28px] border bg-card shadow-float">
                <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                    <span className="flex min-w-0 items-center gap-2.5 font-semibold">
                        <Initials
                            name={props.company}
                            className="size-7 text-caption"
                        />
                        <span className="truncate">{props.company}</span>
                    </span>
                    <span className="text-sm text-primary">Personal</span>
                </div>
                <div className="flex flex-col gap-4 px-4 py-5">
                    <p className="text-sm text-muted-foreground">
                        {props.campaign.closedOn ?? ''} ·{' '}
                        {props.workCenter.name}
                    </p>
                    <p className="text-xl leading-7 font-semibold">
                        Resultados de la evaluación de riesgo psicosocial
                    </p>
                    <p>
                        {answeredLine(
                            props.answered,
                            props.minimum,
                            props.workCenter.name,
                        )}
                    </p>
                    {themes !== null && (
                        <div className="rounded-xl bg-muted px-3.5 py-3">
                            <p className="font-semibold">
                                Lo que más atención necesita
                            </p>
                            <p>{themes}</p>
                        </div>
                    )}
                    <p className="text-sm font-medium text-primary">
                        Ver todas las cifras
                        <span className="block font-normal text-muted-foreground">
                            Cuántos cuestionarios quedan en cada nivel de
                            riesgo, de todo el centro.
                        </span>
                    </p>
                    {withMeasures && props.measures.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <p className="font-semibold">
                                Lo que vamos a hacer
                            </p>
                            {props.measures.map((measure) => (
                                <p key={measure.title}>{measure.title}.</p>
                            ))}
                        </div>
                    )}
                    {props.channel !== null && (
                        <div className="rounded-xl bg-accent px-3.5 py-3">
                            <p className="font-semibold">
                                Si vive violencia o un trato injusto
                            </p>
                            <p>
                                {props.channel.anonymous
                                    ? 'Avise por el canal de quejas, también sin dar su nombre. Nadie recibirá represalias.'
                                    : 'Avise por el canal de quejas. Nadie recibirá represalias.'}
                            </p>
                        </div>
                    )}
                    <p className="text-center text-caption text-muted-foreground">
                        hecho con humetría
                    </p>
                </div>
            </div>
        </aside>
    );
}
