import Humi from '@/components/assistant/humi';
import type { HumiExpression } from '@/lib/assistant/humi-expressions';

/** Humi's face in a round, tinted badge, as it sits in the header, beside its answers and on the launcher. */
export default function HumiAvatar({
    expression = 'hola',
    size = 36,
}: {
    expression?: HumiExpression;
    size?: number;
}) {
    return (
        <span
            className="flex shrink-0 items-center justify-center rounded-full bg-accent"
            style={{ width: size, height: size }}
        >
            <Humi expression={expression} full={false} size={size * 0.62} />
        </span>
    );
}
