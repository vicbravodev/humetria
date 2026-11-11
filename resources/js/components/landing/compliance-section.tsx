import { EyeOff, Lock, Users } from 'lucide-react';
import {
    ICON_BOX,
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import PrivacyDemo from '@/components/landing/privacy-demo';
import Reveal from '@/components/landing/reveal';
import { cn } from '@/lib/utils';

const rules = [
    {
        icon: EyeOff,
        title: 'RH nunca ve respuestas individuales',
        text: 'Solo ve resultados agrupados.',
    },
    {
        icon: Users,
        title: 'Los grupos aparecen solo con 5 o más respuestas',
        text: 'Si un grupo es más chico, se oculta.',
    },
    {
        icon: Lock,
        title: 'La Guía I de la NOM-035 y las listas de la NOM-037 llevan nombre',
        text: 'Porque la norma lo pide. Solo las ven los roles autorizados.',
    },
];

/** Who sees what, said plainly and shown: never more anonymity than each tool gives. */
export default function ComplianceSection() {
    return (
        <section
            id="seccion-confidencialidad"
            aria-labelledby="confidencialidad"
            className={cn('border-y bg-band', SECTION)}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-8 sm:gap-12 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:items-center lg:gap-14',
                )}
            >
                <Reveal className="lg:order-2">
                    <div
                        data-test="compliance-privacy"
                        className="flex flex-col gap-5"
                    >
                        <h2
                            id="confidencialidad"
                            className={cn(TITLE, 'scroll-mt-24')}
                        >
                            La gente contesta con honestidad cuando sabe quién
                            puede ver lo que contesta
                        </h2>
                        <p className={LEAD}>
                            En las encuestas confidenciales, RH nunca ve
                            respuestas individuales. Probar la regla es más
                            rápido que leerla: filtra los resultados en la demo.
                        </p>
                        <ul className="mt-2">
                            {rules.map(({ icon: Icon, title, text }) => (
                                <li
                                    key={title}
                                    className="grid grid-cols-[40px_minmax(0,1fr)] gap-3.5 border-t py-4 last:border-b"
                                >
                                    <span className={`${ICON_BOX} size-10`}>
                                        <Icon aria-hidden className="size-5" />
                                    </span>
                                    <span>
                                        <b className="block leading-snug font-semibold">
                                            {title}
                                        </b>
                                        <span className="text-meta leading-snug text-muted-foreground">
                                            {text}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </Reveal>
                <Reveal delay={0.1}>
                    <PrivacyDemo />
                </Reveal>
            </div>
        </section>
    );
}
