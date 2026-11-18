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

/** Humi in the panel: its film first and wide (it is the proof), then what it answers, in three plain lines. */
export default function HumiSection() {
    return (
        <section id="seccion-humi" aria-labelledby="humi" className={SECTION}>
            <div className={cn(WRAP, 'flex flex-col gap-8 lg:gap-12')}>
                <Reveal className="flex max-w-[820px] flex-col gap-5">
                    <LandingHumi size={92} />
                    <h2 id="humi" className={cn(TITLE, 'scroll-mt-24')}>
                        Pregúntale a Humi lo que necesites de tu cuenta
                    </h2>
                    <p className={LEAD}>
                        Humi vive dentro del panel. Le preguntas cómo vas, qué
                        te falta o cuánto costaría un aumento, y te contesta con
                        tus datos y te lleva a la pantalla donde lo resuelves.
                        Nunca te muestra respuestas de nadie.
                    </p>
                </Reveal>
                <div className="w-full max-w-5xl">
                    <LoopVideo
                        name="humetria-humi"
                        test="humi-video"
                        caption="Humi en el panel. Conversación de ejemplo"
                        description="preguntas «¿Qué me falta para estar al día en Planta Querétaro?» y Humi consulta el avance y responde que faltan 31 respuestas de NOM-035 y 3 listas de verificación por validar en NOM-037, con sus fuentes. Luego preguntas cuánto cuesta el escenario de 5.5 % y responde que el punto medio cuesta 5.6 millones el primer año, con un botón que abre el escenario. Al final, Humi solo lee y explica: no cambia datos, no ve respuestas individuales y solo habla de tu empresa."
                        className="aspect-video"
                    />
                </div>
                <ul className="grid gap-5 border-t pt-6 md:grid-cols-3 md:gap-10">
                    {facts.map((fact) => (
                        <li key={fact} className="max-w-[34ch] leading-snug">
                            {fact}
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
