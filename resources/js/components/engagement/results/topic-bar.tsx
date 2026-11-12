import BarMeter from '@/components/humetria/bar-meter';
import ProtectedCell from '@/components/humetria/protected-cell';
import { pointsText } from '@/lib/engagement/format';

/**
 * One favorable share as a bar with its reference (the company's engagement, the previous wave): the text it measures,
 * the figure and how far it is from the reference in points. Under the minimum, «Protegido» and no bar.
 */
export default function TopicBar({
    text,
    favorable,
    reference,
    index,
}: {
    /** The question; nothing when the row already says what it measures. */
    text: string | null;
    favorable: number | null;
    reference: number | null;
    index: number;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-4 text-sm">
                <span className="text-pretty">{text}</span>
                {favorable === null ? (
                    <ProtectedCell className="shrink-0" />
                ) : (
                    <span className="shrink-0 whitespace-nowrap tabular-nums">
                        <strong className="text-base font-semibold">
                            {favorable} %
                        </strong>
                        {reference !== null && (
                            <span className="ml-1.5 text-muted-foreground">
                                {pointsText(favorable - reference)}
                            </span>
                        )}
                    </span>
                )}
            </div>
            {favorable !== null && (
                <BarMeter
                    percent={favorable}
                    tooltip={`${text ?? 'Tema'}: ${favorable} %`}
                    index={index}
                    {...(reference === null ? {} : { reference })}
                />
            )}
        </div>
    );
}
