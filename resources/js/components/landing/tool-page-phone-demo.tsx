import { Check } from 'lucide-react';
import { AnimatePresence, m } from 'motion/react';
import { EASE_OUT } from '@/components/landing/landing-motion';

type Props = {
    shot: string;
    alt: string;
    /** The step being shown, and its title. */
    step: number;
    title: string;
};

/**
 * The phone of the person, as the steps play: the step's name drops in at the top, a tap ripples on the button while the
 * person acts, and a check lands when it is done. Motion respects reduced motion (MotionConfig on the page).
 */
export default function ToolPagePhoneDemo({ shot, alt, step, title }: Props) {
    return (
        <div className="relative mx-auto w-60 sm:w-64" data-test="phone-demo">
            <img
                src={`/images/tools/${shot}.webp`}
                alt={alt}
                width={585}
                height={1266}
                loading="lazy"
                className="block w-full rounded-[34px] border-[7px] border-foreground bg-foreground shadow-raised"
            />
            <AnimatePresence mode="wait">
                <m.p
                    key={step}
                    initial={{ opacity: 0, y: -8, filter: 'blur(4px)' }}
                    animate={{ opacity: 1, y: 0, filter: 'blur(0px)' }}
                    exit={{ opacity: 0, y: -6 }}
                    transition={{ duration: 0.3, ease: EASE_OUT }}
                    className="absolute inset-x-4 top-10 rounded-full bg-foreground/90 px-3 py-1.5 text-center text-caption font-semibold text-background shadow-raised"
                >
                    Paso {step + 1} · {title}
                </m.p>
            </AnimatePresence>
            {step === 1 && (
                <m.span
                    aria-hidden
                    initial={{ opacity: 0.7, scale: 0.6 }}
                    animate={{ opacity: 0, scale: 1.6 }}
                    transition={{
                        duration: 1.1,
                        ease: EASE_OUT,
                        repeat: Infinity,
                        repeatDelay: 0.3,
                    }}
                    className="absolute bottom-[9%] left-1/2 -ml-6 size-12 rounded-full border-2 border-family bg-family/25"
                />
            )}
            <AnimatePresence>
                {step === 2 && (
                    <m.span
                        aria-hidden
                        initial={{ opacity: 0, scale: 0.6 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.8 }}
                        transition={{
                            type: 'spring',
                            duration: 0.5,
                            bounce: 0.3,
                        }}
                        className="absolute top-1/2 left-1/2 -mt-8 -ml-8 grid size-16 place-items-center rounded-full bg-family text-background shadow-raised"
                    >
                        <Check className="size-8" />
                    </m.span>
                )}
            </AnimatePresence>
        </div>
    );
}
