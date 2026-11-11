import { useTheme } from '../../kit/themes';

const RAYS = [
    [63, 114, 49, 106],
    [104, 73, 96, 59],
    [160, 58, 160, 42],
    [216, 73, 224, 59],
    [257, 114, 271, 106],
] as const;
const BASE = [1, 1, 1, 0.25, 0.25] as const;

/** Humi como lo dibuja el panel: cuerpo, dos ojos y los rayos del arco del logo. `look` mueve las pupilas; `think` enciende los rayos en ola. */
export const Mascot = ({ f, width, dx = 0, dy = 0, think = 0 }: { f: number; width: number; dx?: number; dy?: number; think?: number }) => {
    const c = useTheme();
    const phase = f % 96;
    const blink = phase < 7 ? Math.sin((phase / 7) * Math.PI) : 0;
    const bob = Math.sin((2 * Math.PI * f) / 125) * 4;
    return (
        <svg width={width} height={(width * 340) / 320} viewBox="0 0 320 340" style={{ overflow: 'visible' }}>
            <g transform={`translate(0 ${bob})`}>
                <g stroke={c.primary} strokeWidth="8" strokeLinecap="round">
                    {RAYS.map(([x1, y1, x2, y2], i) => {
                        const wave = 0.5 + 0.5 * Math.sin((2 * Math.PI * f) / 22 - i * 1.15);
                        const base = BASE[i] ?? 1;
                        return <line key={x1} x1={x1} y1={y1} x2={x2} y2={y2} opacity={base + (0.2 + 0.8 * wave - base) * think} />;
                    })}
                </g>
                <path d="M60 276 V170 A100 100 0 0 1 260 170 V276 A24 24 0 0 1 236 300 H84 A24 24 0 0 1 60 276 Z" fill={c.primary} />
                <g transform={`translate(0 170) scale(1 ${1 - 0.9 * blink}) translate(0 -170)`}>
                    <circle cx="134" cy="170" r="40" fill="#f4fbf9" />
                    <circle cx="186" cy="170" r="40" fill="#f4fbf9" />
                    <g transform={`translate(${dx} ${dy})`}>
                        <circle cx="134" cy="174" r="11" fill="#0e1a18" />
                        <circle cx="186" cy="174" r="11" fill="#0e1a18" />
                    </g>
                </g>
                <path d="M140 234 Q160 252 180 234" fill="none" stroke="#f4fbf9" strokeWidth="7" strokeLinecap="round" />
            </g>
        </svg>
    );
};
