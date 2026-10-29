import SectionHeading from '@/components/landing/section-heading';

const steps = [
    {
        title: 'Cargas a tu gente y tus centros',
        text: 'Subes a las personas de cada centro de trabajo y Humetría te dice qué le toca a cada uno.',
    },
    {
        title: 'Humetría invita a cada persona',
        text: 'Cada quien recibe su propia invitación, con un enlace que sirve una sola vez, y contesta desde su celular o su computadora.',
    },
    {
        title: 'Humetría recolecta y ordena',
        text: 'Las respuestas llegan solas, con la confidencialidad que pide cada norma, y ves el avance de cada centro.',
    },
    {
        title: 'Recibes el diagnóstico, el plan y la evidencia',
        text: 'Resultados por grupo, las acciones que siguen y todo listo para presentar ante la STPS.',
    },
] as const;

export default function HowItWorksSection() {
    return (
        <section aria-labelledby="como-funciona" className="border-y bg-muted">
            <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <SectionHeading id="como-funciona" title="Cómo funciona">
                    El mismo camino para cada herramienta: de la lista de tu
                    gente a la evidencia.
                </SectionHeading>

                <ol className="mt-10 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="border-t border-input pt-6"
                        >
                            <span className="text-sm font-medium text-primary tabular-nums">
                                Paso {index + 1}
                            </span>
                            <h3 className="mt-2 text-lg leading-7 font-semibold">
                                {step.title}
                            </h3>
                            <p className="mt-2 text-muted-foreground">
                                {step.text}
                            </p>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
