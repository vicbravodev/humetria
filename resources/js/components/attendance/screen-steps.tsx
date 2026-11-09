const STEPS = [
    {
        title: 'Abre el enlace en la tableta',
        text: 'Cópialo y envíalo a la tableta, o escribe la dirección en su navegador.',
    },
    {
        title: 'Déjala fija en la entrada',
        text: 'Pantalla encendida y a la vista. No necesita que nadie inicie sesión.',
    },
    {
        title: 'Verás «Activa» aquí',
        text: 'Y la hora de su última señal. Recarga la página para verla.',
    },
];

/** The three things to do with a screen just issued, until one shows the code (board A2). */
export default function ScreenSteps() {
    return (
        <ol className="flex flex-col gap-4 card p-5">
            {STEPS.map((step, index) => (
                <li key={step.title} className="flex items-start gap-3.5">
                    <span
                        aria-hidden
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-semibold first:bg-primary first:text-primary-foreground"
                    >
                        {index + 1}
                    </span>
                    <div className="flex flex-col">
                        <span className="font-semibold">{step.title}</span>
                        <span className="text-sm text-muted-foreground">
                            {step.text}
                        </span>
                    </div>
                </li>
            ))}
        </ol>
    );
}
