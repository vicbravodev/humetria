import { m } from 'motion/react';
import HowItWorksStep from '@/components/landing/how-it-works-step';
import { EASE_OUT, ONCE } from '@/components/landing/landing-motion';

const steps = [
    {
        title: 'Carga a tu gente',
        text: 'Sube a las personas de cada centro de trabajo y Humetría te dice qué le toca a cada uno.',
        detail: 'plantilla.xlsx · 120 personas',
    },
    {
        title: 'Humetría invita y recuerda',
        text: 'Cada persona recibe su propio enlace, que sirve una sola vez, y contesta desde su celular, con confianza.',
        detail: 'Enlace de un solo uso',
    },
    {
        title: 'Recibe el diagnóstico',
        text: 'Resultados por grupo, el plan de acción y la evidencia lista para presentar ante la STPS.',
        detail: 'Expediente listo',
    },
];

export default function HowItWorksSection() {
    return (
        <section
            id="seccion-como-funciona"
            aria-labelledby="como-funciona"
            className="border-y bg-sidebar"
        >
            <div className="mx-auto flex max-w-[1240px] flex-col gap-10 px-4 py-20 sm:px-6">
                <div className="flex flex-col gap-3">
                    <h2
                        id="como-funciona"
                        className="scroll-mt-24 text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] sm:text-[44px]"
                    >
                        Cómo funciona
                    </h2>
                    <p className="max-w-[52ch] text-lg leading-7 text-muted-foreground">
                        Tú cargas a tu gente una vez. Lo demás lo lleva
                        Humetría, centro por centro.
                    </p>
                </div>
                <div className="relative">
                    {/* The thread that joins the three steps draws itself from left to right. */}
                    <m.span
                        aria-hidden
                        initial={{ transform: 'scaleX(0)' }}
                        whileInView={{ transform: 'scaleX(1)' }}
                        viewport={ONCE}
                        transition={{ duration: 1.2, ease: EASE_OUT }}
                        className="absolute top-5 right-0 left-0 hidden h-px origin-left bg-primary/40 lg:block"
                    />
                    <ol className="grid gap-12 lg:grid-cols-3 lg:gap-10">
                        {steps.map((step, index) => (
                            <HowItWorksStep
                                key={step.title}
                                step={step}
                                index={index}
                            />
                        ))}
                    </ol>
                </div>
            </div>
        </section>
    );
}
