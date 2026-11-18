import { ChevronDown } from 'lucide-react';
import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import { workday2027 } from '@/routes/guides';

const FAQS = [
    {
        question: '¿Qué normas me tocan según mi tamaño?',
        answer: 'Se aplican por centro de trabajo. En la NOM-035, hasta 15 trabajadores solo la Guía I; de 16 a 50, las Guías I y II; más de 50, las Guías I y III. La NOM-037 aplica a quien teletrabaja más del 40 % de su jornada, y el registro de jornada, a todo patrón desde el 1 de enero de 2027.',
    },
    {
        question: '¿Tengo que contratar las nueve herramientas?',
        answer: 'No. Activas las que necesitas y agregas las demás cuando quieras; tu plantilla ya está cargada.',
    },
    {
        question: '¿Mi gente necesita instalar algo o crear una cuenta?',
        answer: 'No. Contesta o marca desde el navegador de su celular, con su enlace o con una llave de acceso que crea una sola vez.',
    },
    {
        question: '¿Humetría certifica que cumplo?',
        answer: 'No. Solo la autoridad puede hacerlo. Humetría te ayuda a aplicar lo que pide cada norma y a reunir la evidencia.',
    },
    {
        question: '¿Quién puede ver las respuestas?',
        answer: 'Nadie ve una respuesta anónima individual. RH ve resultados de grupos de 5 o más. Lo que la norma pide identificado solo lo ven los roles autorizados.',
    },
    {
        question: '¿Funciona para un consultor con varias empresas?',
        answer: 'Sí. Cada empresa tiene sus datos aparte y tú entras a todas desde tu cuenta.',
    },
];

/** The questions asked before a demo, beside the guide to the workday of 2027. */
export default function LandingFaq() {
    return (
        <section
            id="preguntas"
            aria-labelledby="preguntas-title"
            className={cn('scroll-mt-16 border-t', SECTION)}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-14',
                )}
            >
                <div className="grid content-start gap-5">
                    <h2 id="preguntas-title" className={TITLE}>
                        Preguntas frecuentes
                    </h2>
                    <a
                        href={workday2027().url}
                        data-test="guide-link"
                        className="grid gap-1.5 rounded-2xl bg-band p-5 transition-colors hover:bg-accent"
                    >
                        <span className="text-meta font-semibold text-primary">
                            Guía
                        </span>
                        <b className="text-lg leading-snug">
                            Qué cambia con el registro de jornada en 2027
                        </b>
                        <span className="text-muted-foreground">
                            Artículo por artículo, con lo que conviene preparar
                            antes de enero.
                        </span>
                    </a>
                </div>
                <div className="border-t">
                    {FAQS.map((faq, index) => (
                        <details
                            key={faq.question}
                            open={index === 0}
                            className="group border-b"
                        >
                            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-lg leading-snug font-semibold [&::-webkit-details-marker]:hidden">
                                {faq.question}
                                <ChevronDown
                                    aria-hidden
                                    className="size-5 shrink-0 text-muted-foreground transition-transform duration-200 ease-out group-open:rotate-180 motion-reduce:transition-none"
                                />
                            </summary>
                            <p className="max-w-[70ch] pb-5 leading-relaxed text-pretty text-muted-foreground">
                                {faq.answer}
                            </p>
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}
