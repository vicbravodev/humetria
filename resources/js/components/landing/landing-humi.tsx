/** Humi, the assistant, as the panel draws it: the body, its two lens eyes and the ticks of the logo's arch. It floats and blinks. */
export default function LandingHumi({ size = 96 }: { size?: number }) {
    return (
        <svg
            width={size}
            height={size * (340 / 320)}
            viewBox="0 0 320 340"
            role="img"
            aria-label="Humi, asistente de Humetría"
            className="block overflow-visible"
        >
            <g className="humi-bob">
                <g
                    strokeWidth={8}
                    strokeLinecap="round"
                    className="stroke-primary"
                >
                    <line x1="63" y1="114" x2="49" y2="106" />
                    <line x1="104" y1="73" x2="96" y2="59" />
                    <line x1="160" y1="58" x2="160" y2="42" />
                    <line x1="216" y1="73" x2="224" y2="59" opacity="0.25" />
                    <line x1="257" y1="114" x2="271" y2="106" opacity="0.25" />
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
                <path
                    d="M60 276 V170 A100 100 0 0 1 260 170 V276 A24 24 0 0 1 236 300 H84 A24 24 0 0 1 60 276 Z"
                    className="fill-primary"
                />
                <circle
                    cx="134"
                    cy="170"
                    r="40"
                    className="fill-(--humi-eye)"
                />
                <circle
                    cx="186"
                    cy="170"
                    r="40"
                    className="fill-(--humi-eye)"
                />
                <g className="humi-blink">
                    <circle
                        cx="134"
                        cy="174"
                        r="11"
                        className="fill-(--humi-pupil)"
                    />
                    <circle
                        cx="186"
                        cy="174"
                        r="11"
                        className="fill-(--humi-pupil)"
                    />
                </g>
                <path
                    d="M140 234 Q160 252 180 234"
                    fill="none"
                    strokeWidth={7}
                    strokeLinecap="round"
                    className="stroke-(--humi-eye)"
                />
            </g>
        </svg>
    );
}
