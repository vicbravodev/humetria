import { m } from 'motion/react';
import { EASE_OUT } from '@/components/landing/landing-motion';

const WORDS = 'Del celular de tu gente al expediente de la STPS'.split(' ');

/** The promise, word by word: each one rises out of a blur 50 ms after the last, as the page opens. */
export default function HeroHeadline() {
    return (
        <h1
            id="hero-title"
            className="max-w-[13ch] text-[42px] leading-[1.03] font-semibold tracking-[-0.035em] text-balance sm:text-[60px] lg:max-w-[11ch] lg:text-[72px] xl:text-[80px]"
        >
            {WORDS.map((word, index) => (
                <m.span
                    key={`${word}-${index}`}
                    initial={{
                        opacity: 0,
                        transform: 'translateY(0.35em)',
                        filter: 'blur(6px)',
                    }}
                    animate={{
                        opacity: 1,
                        transform: 'translateY(0em)',
                        filter: 'blur(0px)',
                    }}
                    transition={{
                        duration: 0.7,
                        ease: EASE_OUT,
                        delay: 0.1 + index * 0.05,
                    }}
                    className={
                        word === 'STPS'
                            ? 'inline-block text-primary'
                            : 'inline-block'
                    }
                >
                    {word}
                    {index < WORDS.length - 1 ? ' ' : null}
                </m.span>
            ))}
        </h1>
    );
}
