import LoopVideo from '@/components/landing/loop-video';

/** The narrated film of the hero, clear of anything over it. */
export default function HeroFilm() {
    return (
        <LoopVideo
            name="humetria-hero"
            test="hero-video"
            caption="Así funciona, en 45 segundos. Con voz"
            description="cargas a tu gente una sola vez, eliges entre nueve herramientas cuáles aplicar, cada persona contesta desde su celular con un enlace de un solo uso, los resultados llegan por grupos sin nombres y todo queda en un expediente."
            className="aspect-video"
        />
    );
}
