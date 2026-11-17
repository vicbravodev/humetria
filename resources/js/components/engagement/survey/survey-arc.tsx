import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import ArcMeter from '@/components/humetria/arc-meter';

/**
 * The arch of the logo that fills from empty to how far the person came as the screen appears: in 300 ms, the limit of
 * the design system for an interface (ArcMeter's own 700 ms is for the panel); with reduced motion it is just there.
 */
export default function SurveyArc({
    value,
    max,
    label,
    children,
}: {
    value: number;
    max: number;
    label: string;
    /** What goes inside, at the foot of the arch: the check, or the figure and what it counts. */
    children: ReactNode;
}) {
    const [grown, setGrown] = useState(false);

    useEffect(() => {
        // Two frames: the empty arch is painted first, so reaching the value is a transition.
        let frame = requestAnimationFrame(() => {
            frame = requestAnimationFrame(() => setGrown(true));
        });

        return () => cancelAnimationFrame(frame);
    }, []);

    return (
        <div className="animate-in duration-200 ease-out fade-in zoom-in-96 [&_path:last-child]:duration-300">
            <ArcMeter
                value={grown ? value : 0}
                max={max}
                label={label}
                size="md"
            >
                {children}
            </ArcMeter>
        </div>
    );
}
