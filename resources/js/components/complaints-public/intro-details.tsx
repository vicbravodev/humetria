import { ChevronRight } from 'lucide-react';

/** What the person may unfold before writing: what can still identify them, and the procedure the company wrote. */
export default function IntroDetails({ procedure }: { procedure: string }) {
    return (
        <>
            <details className="group">
                <summary className="flex min-h-11 cursor-pointer items-center gap-1.5 font-medium text-primary [&::-webkit-details-marker]:hidden">
                    <ChevronRight
                        aria-hidden
                        className="size-4 shrink-0 transition-transform group-open:rotate-90"
                    />
                    Qué puede identificarle aunque no dé su nombre
                </summary>
                <ul className="mt-1 list-disc space-y-1 pl-5 text-muted-foreground">
                    <li>
                        Detalles que solo usted conoce o su forma de escribir.
                    </li>
                    <li>Si el centro o el grupo es pequeño.</li>
                    <li>
                        La red de la empresa. Si le preocupa, use los datos de
                        su celular.
                    </li>
                </ul>
            </details>
            <details className="group">
                <summary className="flex min-h-11 cursor-pointer items-center gap-1.5 font-medium text-primary [&::-webkit-details-marker]:hidden">
                    <ChevronRight
                        aria-hidden
                        className="size-4 shrink-0 transition-transform group-open:rotate-90"
                    />
                    Cómo se atiende su queja
                </summary>
                <p className="mt-1 whitespace-pre-line text-muted-foreground">
                    {procedure}
                </p>
            </details>
        </>
    );
}
