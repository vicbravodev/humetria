// Tokens de movimiento del Humetria Motion Kit. Los de color y forma viven en ../theme (espejo del design system).
// Ninguna composición pone duraciones o curvas a mano: se toman de aquí.
export { c, radius, risk, floatShadow } from '../theme';

import pacingJson from './pacing.json';

export const FPS = pacingJson.fps;
export const FORMAT_16x9 = { width: 1920, height: 1080 } as const;

/** Duraciones en frames a 30 fps. */
export const dur = {
    micro: 8, // foco, tilde
    enter: 18, // entrada estándar de un elemento
    scene: 24, // entrada y salida de escena
    morph: 36, // un elemento que cambia de lugar o de estado
    count: 54, // un número que cuenta
} as const;

/** Desfase entre elementos de un mismo grupo. */
export const stagger = 5;

/** Ritmo de la narración: aire antes y después de cada línea, y cierre. */
export const pacing = { lead: pacingJson.lead, tail: pacingJson.tail, outro: pacingJson.outro } as const;
