import { useTheme } from './themes';

/** QR decorativo (no es un código real): los tres cuadros de posición y datos pseudoaleatorios fijos. */
const N = 25;
/** Matriz decorativa con los tres cuadros de posición de un QR (no es un código real). */
const QR: readonly (readonly boolean[])[] = (() => {
    let s = 7;
    const rnd = (): number => {
        s = (s * 1103515245 + 12345) % 2147483648;
        return s / 2147483648;
    };
    const finder = (r: number, k: number): boolean | null => {
        const inBox = (r0: number, k0: number): boolean | null => {
            const y = r - r0;
            const x = k - k0;
            if (y < 0 || y > 6 || x < 0 || x > 6) {
                return null;
            }
            return y === 0 || y === 6 || x === 0 || x === 6 || (y >= 2 && y <= 4 && x >= 2 && x <= 4);
        };
        return inBox(0, 0) ?? inBox(0, N - 7) ?? inBox(N - 7, 0);
    };
    return Array.from({ length: N }, (_, r) =>
        Array.from({ length: N }, (_, k) => {
            const quiet = (r < 8 && (k < 8 || k > N - 9)) || (r > N - 9 && k < 8);
            return finder(r, k) ?? (quiet ? false : rnd() > 0.5);
        }),
    );
})();

export const QrCode = ({ size }: { size: number }) => {
    const c = useTheme();
    const m = size / N;
    return (
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ display: 'block' }}>
            {QR.flatMap((row, r) => row.map((on, k) => (on ? <rect key={`${r}-${k}`} x={k * m} y={r * m} width={m + 0.4} height={m + 0.4} fill={c.fg} /> : null)))}
        </svg>
    );
};


