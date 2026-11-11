import { EyeOff, FileText, Link2, Users } from 'lucide-react';
import { WRAP } from '@/components/landing/landing-layout';

const promises = [
    {
        icon: Link2,
        title: 'Enlace de un solo uso',
        text: 'Cada persona contesta desde su celular.',
    },
    {
        icon: EyeOff,
        title: 'RH no ve respuestas individuales',
        text: 'Solo resultados por grupo.',
    },
    {
        icon: Users,
        title: 'Grupos de 5 o más',
        text: 'Los grupos más chicos se ocultan.',
    },
    {
        icon: FileText,
        title: 'Ordena evidencia',
        text: 'No certifica el cumplimiento.',
    },
];

/** The four promises Humetría keeps with your people, right under the hero. */
export default function TrustStrip() {
    return (
        <section
            aria-label="Cómo cuida Humetría a tu gente"
            data-test="trust"
            className="border-y"
        >
            <ul className={`${WRAP} grid grid-cols-2 lg:grid-cols-4`}>
                {promises.map(({ icon: Icon, title, text }) => (
                    <li
                        key={title}
                        className="grid content-start gap-2.5 py-5 pr-3 text-meta leading-snug even:border-l even:pl-4 nth-[n+3]:border-t lg:border-t-0! lg:px-6 lg:py-7 lg:not-first:border-l lg:first:pl-0"
                    >
                        <Icon aria-hidden className="size-6 text-primary" />
                        <span>
                            <b className="block font-semibold">{title}</b>
                            <span className="text-sm text-muted-foreground lg:text-meta">
                                {text}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
