import { m, useReducedMotion } from 'motion/react';
import { EASE_OUT } from '@/components/landing/landing-motion';
import LoopVideoControls from '@/components/landing/loop-video-controls';
import { useAppearance } from '@/hooks/use-appearance';
import { useLoopVideo } from '@/hooks/use-loop-video';
import { cn } from '@/lib/utils';

type Props = {
    /** The film's files in public/video: `{name}.webm`, `{name}.mp4` and the poster `{name}.jpg`, and the same with `-light` for the light theme (rendered in video/). */
    name: string;
    /** What shows under the film. */
    caption: string;
    /** What the film shows, for screen readers. */
    description: string;
    test: string;
    className?: string;
};

/**
 * A narrated film rendered with Remotion: it starts muted, loops and plays inline, with a button to pause it (WCAG 2.2.2),
 * another to turn the voice on and one to watch it again from the start. WebM first for
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
    const {
        attach,
        seek,
        playing,
        toggle,
        muted,
        toggleSound,
        onPlay,
        onPause,
    } = useLoopVideo();
    const { resolvedAppearance } = useAppearance();
    const film = resolvedAppearance === 'light' ? `${name}-light` : name;

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
                    key={film}
                    ref={attach}
                    autoPlay={!still}
                    muted
                    loop
                    playsInline
                    preload="metadata"
                    poster={`/video/${film}.jpg`}
                    onPlay={onPlay}
                    onPause={onPause}
                    className={cn('block w-full', className)}
                >
                    <source src={`/video/${film}.webm`} type="video/webm" />
                    <source src={`/video/${film}.mp4`} type="video/mp4" />
                </video>
            </m.div>
            <figcaption className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                <span>
                    {caption}
                    <span className="sr-only">: {description}</span>
                </span>
                <LoopVideoControls
                    test={test}
                    playing={playing}
                    muted={muted}
                    onRestart={() => seek(0)}
                    onToggle={toggle}
                    onToggleSound={toggleSound}
                />
            </figcaption>
        </figure>
    );
}
