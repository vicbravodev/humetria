import SectionHeading from '@/components/landing/section-heading';

const steps = [
    {
        title: 'Cargas a tu gente',
        text: 'Subes la lista de trabajadores de cada centro de trabajo y Humetría te dice qué guías le tocan.',
    },
    {
        title: 'Humetría invita a cada persona',
        text: 'Cada trabajador recibe su propia invitación, con un enlace que sirve una sola vez.',
    },
    {
        title: 'Cada quien contesta con confianza',
        text: 'Desde su celular o su computadora, de forma confidencial y sin que nadie de su empresa vea lo que contesta.',
    },
    {
        title: 'Recibes el diagnóstico',
        text: 'Resultados por grupo con su nivel de riesgo y el informe listo para presentar ante la STPS.',
    },
] as const;

export default function HowItWorksSection() {
    return (
        <section aria-labelledby="como-funciona" className="border-y bg-muted">
            <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <SectionHeading id="como-funciona" title="Cómo funciona">
                    Cuatro pasos, de la lista de tu gente al informe.
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
