import { useId } from 'react';
import HumiCrown from '@/components/assistant/humi-crown';
import HumiEyes from '@/components/assistant/humi-eyes';
import {
    litTicks,
    type HumiExpression,
} from '@/lib/assistant/humi-expressions';
import { cn } from '@/lib/utils';

/**
 * Humi, the assistant of Humetría (design system: Humi): a round-shouldered body with two lens eyes and the five ticks of
 * the logo's arch above its head. The expression says what it is doing: hola (rest), escucha (the person writes), calcula
 * (it looks things up) and listo (it finished). The ticks are the risk scale: an answer with a risk lights as many as it
 * has. Its colors are the theme's, so it reads the same in light and dark.
 */
export default function Humi({
    expression = 'hola',
    ticks,
    size = 120,
    full = true,
    animated = false,
    className,
}: {
    expression?: HumiExpression;
    /** 0 to 5 ticks lit (the risk scale); the expression's own by default. */
    ticks?: number;
    size?: number;
    /** false: no feet nor ticks, for the small avatars. */
    full?: boolean;
    /** Floats and blinks softly; it stops by itself with reduced motion. */
    animated?: boolean;
    className?: string;
}) {
    const lensId = `${useId().replaceAll(':', '')}-lens`;

    return (
        <svg
            width={size}
            height={full ? size * (340 / 320) : size * (270 / 220)}
            viewBox={full ? '0 0 320 340' : '50 40 220 270'}
            role="img"
            aria-label="Humi, asistente de Humetría"
            className={cn('block overflow-visible', className)}
        >
            <defs>
                <clipPath id={lensId}>
                    <circle cx="134" cy="170" r="40" />
                </clipPath>
            </defs>
            <g className={animated ? 'humi-bob' : undefined}>
                {full && <HumiCrown lit={litTicks(expression, ticks)} />}
                <path
                    d="M60 276 V170 A100 100 0 0 1 260 170 V276 A24 24 0 0 1 236 300 H84 A24 24 0 0 1 60 276 Z"
                    className="fill-primary"
                />
                <g
                    key={expression}
                    className={animated ? 'humi-change' : undefined}
                >
                    <HumiEyes
                        expression={expression}
                        lensId={lensId}
                        blink={animated}
                    />
                </g>
            </g>
        </svg>
    );
}
