import {
    HUMI_FACES,
    type HumiExpression,
} from '@/lib/assistant/humi-expressions';

/** Humi's two lens eyes, its pupils (or closed eyes in a smile) and its mouth, for an expression. */
export default function HumiEyes({
    expression,
    lensId,
    blink,
}: {
    expression: HumiExpression;
    lensId: string;
    blink: boolean;
}) {
    // While it calculates its pupils look around, as if reading.
    const look = blink && expression === 'calcula';
    const face = HUMI_FACES[expression];
    const pupils = face.pupils;

    return (
        <>
            <circle cx="134" cy="170" r="40" className="fill-(--humi-eye)" />
            <circle cx="186" cy="170" r="40" className="fill-(--humi-eye)" />
            <circle
                cx="186"
                cy="170"
                r="40"
                className="fill-primary"
                opacity={face.lens}
                clipPath={`url(#${lensId})`}
            />
            {pupils === null ? (
                <g
                    strokeWidth="8"
                    strokeLinecap="round"
                    fill="none"
                    className="stroke-(--humi-pupil)"
                >
                    <path d="M118 180 Q134 160 150 180" />
                    <path d="M170 180 Q186 160 202 180" />
                </g>
            ) : (
                <g className={blink ? 'humi-blink' : undefined}>
                    <g className={look ? 'humi-look' : undefined}>
                        {[pupils.lx, pupils.rx].map((cx) => (
                            <g key={cx}>
                                <circle
                                    cx={cx}
                                    cy={pupils.y}
                                    r={pupils.r}
                                    className="fill-(--humi-pupil)"
                                />
                                <circle
                                    cx={cx + pupils.off}
                                    cy={pupils.y - pupils.off}
                                    r={pupils.r > 12 ? 4.5 : 3.5}
                                    className="fill-(--humi-eye)"
                                />
                            </g>
                        ))}
                    </g>
                </g>
            )}
            <path
                d={face.mouth}
                strokeWidth="7"
                strokeLinecap="round"
                fill="none"
                className="stroke-(--humi-mouth)"
            />
        </>
    );
}
