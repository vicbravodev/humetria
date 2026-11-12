import { Check } from 'lucide-react';
import LandingHumi from '@/components/landing/landing-humi';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import LoopVideo from '@/components/landing/loop-video';
import Reveal from '@/components/landing/reveal';
import { cn } from '@/lib/utils';

const facts = [
    'Explica las normas con los datos de tu empresa',
    'Cada respuesta dice de dónde sale',
    'Nunca ve respuestas individuales ni grupos de menos de 5',
];

/** Humi in the panel: what it answers, and its film beside it. */
export default function HumiSection() {
    return (
        <section id="seccion-humi" aria-labelledby="humi" className={SECTION}>
            <div
                className={cn(
                    WRAP,
                    'grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14',
                )}
            >
                <Reveal className="flex flex-col gap-5">
                    <LandingHumi size={92} />
                    <h2 id="humi" className={cn(TITLE, 'scroll-mt-24')}>
                        Pregúntale a Humi lo que necesites de tu cuenta
                    </h2>
                    <p className={LEAD}>
                        Humi vive dentro del panel. Consulta el avance, los
                        resultados agrupados, tus pendientes y los escenarios de
                        Compensación, y te lleva a la pantalla donde se
                        resuelve.
                    </p>
                    <ul className="mt-1 grid gap-3">
                        {facts.map((fact) => (
                            <li
                                key={fact}
                                className="grid grid-cols-[20px_minmax(0,1fr)] gap-2.5 leading-snug"
                            >
                                <Check
                                    aria-hidden
                                    className="mt-0.5 size-5 text-primary"
                                />
                                {fact}
                            </li>
                        ))}
                    </ul>
                </Reveal>
                <LoopVideo
                    name="humetria-humi"
                    test="humi-video"
                    caption="Humi en el panel. Conversación de ejemplo"
                    description="preguntas «¿Qué me falta para estar al día en Planta Querétaro?» y Humi consulta el avance y responde que faltan 31 respuestas de NOM-035 y 3 listas de verificación por validar en NOM-037, con sus fuentes. Luego preguntas cuánto cuesta el escenario de 5.5 % y responde que el punto medio cuesta 5.6 millones el primer año, con un botón que abre el escenario. Al final, Humi solo lee y explica: no cambia datos, no ve respuestas individuales y solo habla de tu empresa."
                    className="aspect-video"
                />
            </div>
        </section>
    );
}
