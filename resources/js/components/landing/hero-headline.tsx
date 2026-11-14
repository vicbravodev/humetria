import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

const WORDS = 'Del celular de tu gente al expediente de la STPS'.split(' ');

/** The promise, word by word: each one rises out of a blur 50 ms after the last, as the page opens (CSS, `enter-word`). */
export default function HeroHeadline() {
    return (
        <h1
            id="hero-title"
            className="max-w-[13ch] text-[42px] leading-[1.03] font-semibold tracking-[-0.035em] text-balance sm:text-[60px] lg:max-w-[11ch] lg:text-[72px] xl:text-[80px]"
        >
            {WORDS.map((word, index) => (
                <span
                    key={`${word}-${index}`}
                    className={cn(
                        'inline-block enter-word',
                        enterDelay(0.1 + index * 0.05),
                        word === 'STPS' && 'text-primary',
                    )}
                >
                    {word}
                    {index < WORDS.length - 1 ? ' ' : null}
                </span>
            ))}
        </h1>
    );
}
