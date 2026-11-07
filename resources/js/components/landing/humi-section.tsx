import { Check } from 'lucide-react';
import HumiChatDemo from '@/components/landing/humi-chat-demo';
import LandingHumi from '@/components/landing/landing-humi';
import Reveal from '@/components/landing/reveal';

const facts = [
    'Explica las normas con los datos de tu empresa',
    'Cada respuesta dice de dónde sale',
    'Nunca ve respuestas individuales ni grupos de menos de 5',
] as const;

export default function HumiSection() {
    return (
        <section aria-labelledby="humi" className="border-y bg-sidebar">
            <div className="mx-auto grid max-w-[1240px] items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-20 lg:py-28">
                <Reveal className="flex flex-col gap-6">
                    <LandingHumi />
                    <h2
                        id="humi"
                        className="scroll-mt-24 text-[32px] leading-[1.1] font-semibold tracking-[-0.025em] text-balance sm:text-[44px]"
                    >
                        Pregúntale a Humi lo que necesites de tu cuenta
                    </h2>
                    <p className="max-w-[48ch] text-lg leading-7 text-muted-foreground">
                        Humi vive dentro del panel. Consulta el avance, los
                        resultados agrupados, tus pendientes y los escenarios de
                        Compensación, y te lleva a la pantalla donde se
                        resuelve.
                    </p>
                    <ul className="flex flex-col gap-2.5">
                        {facts.map((fact) => (
                            <li key={fact} className="flex gap-2.5">
                                <Check
                                    aria-hidden
                                    className="mt-1 size-4 shrink-0 text-primary"
                                />
                                {fact}
                            </li>
                        ))}
                    </ul>
                </Reveal>
                <Reveal delay={0.1}>
                    <HumiChatDemo />
                </Reveal>
            </div>
        </section>
    );
}
