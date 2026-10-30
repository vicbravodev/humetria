import { Info } from 'lucide-react';

/** How the Guía I decides who requires a clinical assessment, and that only the outcome is shown. */
export default function ScreeningRule() {
    return (
        <section className="flex flex-wrap items-start gap-4 rounded-xl border px-[22px] py-5">
            <Info
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
            <div className="flex flex-[1_1_320px] flex-col gap-1">
                <h2 className="text-sm font-semibold">Cómo se decide</h2>
                <p className="text-sm text-muted-foreground">
                    Requiere valoración quien contestó «sí» en la sección I y en
                    alguna de las secciones II, III o IV de la Guía I, como
                    indica la norma. Humetría no muestra las respuestas, solo el
                    resultado.
                </p>
            </div>
        </section>
    );
}
