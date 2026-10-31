const steps = [
    {
        title: 'Carga a tu gente',
        text: 'Sube a las personas de cada centro de trabajo y Humetría te dice qué le toca a cada uno.',
    },
    {
        title: 'Humetría invita y recuerda',
        text: 'Cada persona recibe su propio enlace, que sirve una sola vez, y contesta desde su celular, con confianza.',
    },
    {
        title: 'Recibe el diagnóstico',
        text: 'Resultados por grupo, el plan de acción y la evidencia lista para presentar ante la STPS.',
    },
] as const;

export default function HowItWorksSection() {
    return (
        <section
            aria-labelledby="como-funciona"
            className="mx-auto flex max-w-[1200px] flex-col gap-11 px-4 py-16 sm:px-6 lg:py-24"
        >
            <h2
                id="como-funciona"
                className="scroll-mt-24 text-[28px] leading-9 font-semibold tracking-[-0.015em] sm:text-4xl sm:leading-[44px]"
            >
                Cómo funciona
            </h2>

            <ol className="flex flex-wrap gap-10">
                {steps.map((step, index) => (
                    <li
                        key={step.title}
                        className="flex flex-[1_1_260px] flex-col gap-3"
                    >
                        <span
                            aria-hidden
                            className="flex size-10 items-center justify-center rounded-full border-[1.5px] border-primary font-semibold text-primary tabular-nums"
                        >
                            {index + 1}
                        </span>
                        <h3 className="text-lg leading-7 font-semibold">
                            {step.title}
                        </h3>
                        <p className="text-muted-foreground">{step.text}</p>
                    </li>
                ))}
            </ol>
        </section>
    );
}
