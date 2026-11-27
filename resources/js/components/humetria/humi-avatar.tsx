import Humi from '@/components/humetria/humi';
import type { HumiExpression } from '@/lib/assistant/humi-expressions';
import { cn } from '@/lib/utils';

/** Humi's face in a round, tinted badge, as it sits in the header, beside its answers and on the launcher. */
export default function HumiAvatar({
    expression = 'hola',
    size = 36,
    animated = false,
    className,
}: {
    expression?: HumiExpression;
    size?: number;
    /** Floats and blinks softly. */
    animated?: boolean;
    /** Another tint for the badge, where it sits on the accent itself. */
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full bg-accent',
                className,
            )}
            style={{ width: size, height: size }}
        >
            <Humi
                expression={expression}
                full={false}
                size={size * 0.62}
                animated={animated}
            />
        </span>
    );
}
