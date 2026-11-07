import { m, useReducedMotion } from 'motion/react';
import { Pause, Play } from 'lucide-react';
import { useRef, useState } from 'react';
import { EASE_OUT } from '@/components/landing/landing-motion';
import { cn } from '@/lib/utils';

type Props = {
    /** The film's files in public/video: `{name}.webm`, `{name}.mp4` and the poster `{name}.jpg` (rendered in video/). */
    name: string;
    /** What shows under the film. */
    caption: string;
    /** What the film shows, for screen readers. */
    description: string;
    test: string;
    className?: string;
};

/**
 * A film rendered with Remotion: muted, looping and inline, with a button to pause it (WCAG 2.2.2). WebM first for
 * Chromium, MP4 for Safari. With reduced motion it stays on its poster until the visitor plays it.
 */
export default function LoopVideo({
    name,
    caption,
    description,
    test,
    className,
}: Props) {
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
        <figure data-test={test} className="flex w-full flex-col gap-3">
            {/* Only the frame grows in: the button under it keeps its full 44 px touch. */}
            <m.div
                initial={{ opacity: 0, transform: 'scale(0.96)' }}
                whileInView={{ opacity: 1, transform: 'scale(1)' }}
                viewport={{ once: true, amount: 0.2 }}
                transition={{ duration: 0.9, ease: EASE_OUT, delay: 0.2 }}
                className="overflow-hidden rounded-2xl border bg-foreground shadow-float dark:bg-card"
            >
                <video
                    ref={video}
                    autoPlay={!still}
                    muted
                    loop
                    playsInline
                    preload="metadata"
                    poster={`/video/${name}.jpg`}
                    onPlay={() => setPlaying(true)}
                    onPause={() => setPlaying(false)}
                    className={cn('block w-full', className)}
                >
                    <source src={`/video/${name}.webm`} type="video/webm" />
                    <source src={`/video/${name}.mp4`} type="video/mp4" />
                </video>
            </m.div>
            <figcaption className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                <span>
                    {caption}
                    <span className="sr-only">: {description}</span>
                </span>
                <button
                    type="button"
                    data-test={`${test}-toggle`}
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
        </figure>
    );
}
