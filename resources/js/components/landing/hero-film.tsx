import { Check, Lock } from 'lucide-react';
import HeroFloatCard from '@/components/landing/hero-float-card';
import LoopVideo from '@/components/landing/loop-video';

/** The narrated film of the hero, with the two things it ends in floating over its corners. */
export default function HeroFilm() {
    return (
        <div className="relative">
            <LoopVideo
                name="humetria-hero"
                test="hero-video"
                caption="Así funciona, en 45 segundos. Con voz"
                description="cargas a tu gente una sola vez, eliges entre nueve herramientas cuáles aplicar, cada persona contesta desde su celular con un enlace de un solo uso, los resultados llegan por grupos sin nombres y todo queda en un expediente."
                className="aspect-video"
            />
            <HeroFloatCard
                icon={Lock}
                title="Respuestas confidenciales"
                detail="Grupos de 5 o más"
                delay={1.1}
                className="-top-5 -right-1 sm:-top-6 sm:-right-6 xl:-right-10"
            />
            <HeroFloatCard
                icon={Check}
                title="Informe del numeral 7.7"
                detail="Listo para tu expediente"
                delay={1.25}
                className="bottom-20 -left-1 sm:-left-7 xl:-left-14"
            />
        </div>
    );
}
