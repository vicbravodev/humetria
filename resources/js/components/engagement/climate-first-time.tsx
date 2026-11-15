import CreateSurveyButton from '@/components/engagement/create-survey-button';

const STEPS = [
    {
        title: 'Revisa las preguntas',
        body: 'Son las mismas para todos y se contestan en unos 10 minutos.',
    },
    {
        title: 'Elige a quién invitar',
        body: 'Las personas de tu empresa con correo reciben su enlace, de un solo uso.',
    },
    {
        title: 'Lanza y sigue el avance',
        body: 'Ves cuántas personas contestaron, nunca qué ni quién.',
    },
];

/** No climate survey yet: the three steps to the first one and, for who manages climate, the button to start it. */
export default function ClimateFirstTime({
    canManage,
}: {
    canManage: boolean;
}) {
    return (
        <section
            aria-labelledby="first-climate"
            className="flex flex-col items-center gap-6 rounded-xl border border-dashed bg-card px-6 py-10 text-center"
        >
            <div className="flex flex-col gap-1">
                <h2
                    id="first-climate"
                    className="text-xl leading-7 font-semibold"
                >
                    Todavía no hay una encuesta de clima
                </h2>
                <p className="text-muted-foreground">
                    Nadie recibe nada hasta que la lances.
                </p>
            </div>
            <ol className="grid w-full max-w-3xl gap-4 text-left sm:grid-cols-3">
                {STEPS.map((step, index) => (
                    <li key={step.title} className="flex gap-3">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent font-semibold text-primary">
                            {index + 1}
                        </span>
                        <span className="flex flex-col gap-0.5">
                            <span className="font-medium">{step.title}</span>
                            <span className="text-sm text-muted-foreground">
                                {step.body}
                            </span>
                        </span>
                    </li>
                ))}
            </ol>
            {canManage && (
                <CreateSurveyButton label="Preparar la primera encuesta" />
            )}
        </section>
    );
}
