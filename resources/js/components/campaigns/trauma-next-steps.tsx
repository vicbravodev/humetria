import { HandHeart, Lock, Send } from 'lucide-react';

const STEPS = [
    {
        icon: Send,
        title: 'Cada persona recibe la Guía I',
        text: 'Por correo, con un enlace de un solo uso.',
    },
    {
        icon: Lock,
        title: 'Solo personas autorizadas ven el resultado',
        text: 'Si alguien requiere valoración clínica, aparece en su canalización.',
    },
    {
        icon: HandHeart,
        title: 'Registras la atención',
        text: 'Queda en el expediente que pide el numeral 5.8.',
    },
];

/** What happens after sending Guía I for an event (pantalla Guia-I-Acontecimiento). */
export default function TraumaNextSteps() {
    return (
        <aside
            aria-labelledby="trauma-next"
            className="rounded-xl border px-[22px] pt-5 pb-2"
        >
            <h2 id="trauma-next" className="mb-1 text-sm font-semibold">
                Qué pasa después
            </h2>
            <ol className="divide-y">
                {STEPS.map(({ icon: Icon, title, text }) => (
                    <li key={title} className="flex items-start gap-3.5 py-3.5">
                        <span className="inline-flex size-[34px] shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                            <Icon aria-hidden className="size-[18px]" />
                        </span>
                        <span className="flex flex-col">
                            <span className="text-sm font-medium">{title}</span>
                            <span className="text-[13px] leading-[18px] text-muted-foreground">
                                {text}
                            </span>
                        </span>
                    </li>
                ))}
            </ol>
        </aside>
    );
}
