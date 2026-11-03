import { HUMI_TICKS } from '@/lib/assistant/humi-expressions';

/** The ticks of Humi's arch (as many lit as the risk scale says) and its two feet. */
export default function HumiCrown({ lit }: { lit: number }) {
    return (
        <>
            <g strokeWidth="8" strokeLinecap="round" className="stroke-primary">
                {HUMI_TICKS.map(([x1, y1, x2, y2], index) => (
                    <line
                        key={x1}
                        x1={x1}
                        y1={y1}
                        x2={x2}
                        y2={y2}
                        opacity={index < lit ? 1 : 0.25}
                    />
                ))}
            </g>
            <rect
                x="92"
                y="294"
                width="52"
                height="22"
                rx="11"
                className="fill-primary"
            />
            <rect
                x="176"
                y="294"
                width="52"
                height="22"
                rx="11"
                className="fill-primary"
            />
        </>
    );
}
