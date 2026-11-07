import { m, useReducedMotion } from 'motion/react';
import { Pause, Play } from 'lucide-react';
import { useRef, useState } from 'react';
import { EASE_OUT } from '@/components/landing/landing-motion';

/**
 * The film of how Humetría works (video/, rendered with Remotion): muted, looping and inline, with a button to pause it
 * (WCAG 2.2.2). With reduced motion it stays on its poster until the visitor plays it.
 */
export default function HeroVideo() {
    const still = useReducedMotion() === true;
    const video = useRef<HTMLVideoElement>(null);
    // What the button offers follows the video itself: autoplay can be refused, and then it stays paused.
    const [playing, setPlaying] = useState(false);

    const toggle = (): void => {
        const element = video.current;

        if (element === null) {
            return;
        }

        if (element.paused) {
            element.play().catch(() => setPlaying(false));
        } else {
            element.pause();
        }
    };

    return (
        <m.figure
            data-test="hero-video"
            initial={{ opacity: 0, transform: 'scale(0.96)' }}
            animate={{ opacity: 1, transform: 'scale(1)' }}
            transition={{ duration: 0.9, ease: EASE_OUT, delay: 0.35 }}
            className="flex w-full flex-col gap-3"
        >
            <div className="overflow-hidden rounded-2xl border bg-foreground shadow-float dark:bg-card">
                <video
                    ref={video}
                    autoPlay={!still}
                    muted
                    loop
                    playsInline
                    preload="metadata"
                    poster="/video/humetria-hero.jpg"
                    onPlay={() => setPlaying(true)}
                    onPause={() => setPlaying(false)}
                    className="block aspect-[4/3] w-full"
                >
                    <source src="/video/humetria-hero.webm" type="video/webm" />
                    <source src="/video/humetria-hero.mp4" type="video/mp4" />
                </video>
            </div>
            <figcaption className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                <span>
                    Así funciona, en 40 segundos
                    <span className="sr-only">
                        : cargas a tu gente, cada persona contesta desde su
                        celular, los resultados se agrupan sin nombres y el
                        expediente queda listo para la STPS.
                    </span>
                </span>
                <button
                    type="button"
                    data-test="hero-video-toggle"
                    aria-label={playing ? 'Pausar video' : 'Reproducir video'}
                    onClick={toggle}
                    className="flex size-11 shrink-0 items-center justify-center rounded-full border bg-card text-foreground transition-transform duration-150 ease-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring active:scale-[0.94]"
                >
                    {playing ? (
                        <Pause aria-hidden className="size-4" />
                    ) : (
                        <Play aria-hidden className="size-4 translate-x-px" />
                    )}
                </button>
            </figcaption>
        </m.figure>
    );
}
