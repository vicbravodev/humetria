import { WRAP } from '@/components/landing/landing-layout';

const promises = [
    {
        title: 'Enlace de un solo uso',
        text: 'Cada persona contesta desde su celular.',
    },
    {
        title: 'RH no ve respuestas individuales',
        text: 'Solo resultados por grupo.',
    },
    {
        title: 'Grupos de 5 o más',
        text: 'Los grupos más chicos se ocultan.',
    },
    {
        title: 'Reúne tus comprobantes',
        text: 'No certifica el cumplimiento.',
    },
];

/** The four promises Humetría keeps with your people, right under the hero: plain text between hairlines. */
export default function TrustStrip() {
    return (
        <section
            aria-label="Cómo cuida Humetría a tu gente"
            data-test="trust"
            className="border-y"
        >
            <ul className={`${WRAP} grid grid-cols-2 lg:grid-cols-4`}>
                {promises.map(({ title, text }) => (
                    <li
                        key={title}
                        className="grid content-start gap-1 py-5 pr-3 text-meta leading-snug even:border-l even:pl-4 nth-[n+3]:border-t lg:border-t-0! lg:px-6 lg:py-7 lg:not-first:border-l lg:first:pl-0"
                    >
                        <b className="block font-semibold">{title}</b>
                        <span className="text-sm text-muted-foreground lg:text-meta">
                            {text}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
