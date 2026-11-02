export type HumiExpression = 'hola' | 'escucha' | 'calcula' | 'listo';

type Pupils = { lx: number; rx: number; y: number; r: number; off: number };

export type HumiFace = {
    /** How many ticks of the arch are lit by default. */
    ticks: number;
    /** The pupils; none when the eyes are closed in a smile. */
    pupils: Pupils | null;
    mouth: string;
    /** How strong the tint of the right lens is. */
    lens: number;
};

/** The five ticks of the arch, as [x1, y1, x2, y2], from the left one to the right one. */
export const HUMI_TICKS: readonly (readonly [
    number,
    number,
    number,
    number,
])[] = [
    [63, 114, 49, 106],
    [104, 73, 96, 59],
    [160, 58, 160, 42],
    [216, 73, 224, 59],
    [257, 114, 271, 106],
];

export const HUMI_FACES: Record<HumiExpression, HumiFace> = {
    hola: {
        ticks: 3,
        pupils: { lx: 134, rx: 186, y: 174, r: 11, off: 4 },
        mouth: 'M140 234 Q160 252 180 234',
        lens: 0.22,
    },
    escucha: {
        ticks: 1,
        pupils: { lx: 140, rx: 180, y: 174, r: 14, off: 5 },
        mouth: 'M148 238 H172',
        lens: 0.5,
    },
    calcula: {
        ticks: 3,
        pupils: { lx: 142, rx: 194, y: 162, r: 11, off: 4 },
        mouth: 'M146 240 Q160 232 178 236',
        lens: 0.22,
    },
    listo: {
        ticks: 5,
        pupils: null,
        mouth: 'M134 228 Q160 258 186 228',
        lens: 0.22,
    },
};

/** The ticks to light: the ones asked for (the risk scale), or the expression's own, whole and between none and five. */
export function litTicks(expression: HumiExpression, ticks?: number): number {
    return Math.min(
        5,
        Math.max(0, Math.round(ticks ?? HUMI_FACES[expression].ticks)),
    );
}
