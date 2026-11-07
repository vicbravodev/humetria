// Línea de tiempo de HumiChat (frames, 30 fps). El último estado es idéntico al frame 0.
export const HC = {
    size: 1080,
    q1Type: 34,
    q1Send: 96,
    tool1: 110,
    tool1Done: 136,
    tool2: 140,
    tool2Done: 166,
    ans1: 174,
    chips1: 222,
    btn1: 232,
    press1: 292,
    q2Type: 330,
    q2Send: 376,
    tool3: 390,
    tool3Done: 418,
    ans2: 426,
    card2: 478,
    out: 640,
    calmIn: 666,
    end: 750,
} as const;

export const Q1 = '¿Qué me falta para estar al día en Planta Querétaro?';
export const Q2 = '¿Cuánto cuesta el escenario de 5.5\u00A0%?';
export const A1 = 'Te faltan 31 respuestas de NOM-035 y 3 listas de verificación por validar en NOM-037.';
export const A2 = 'El punto medio cuesta $5.6\u00A0M el primer año, $1.5\u00A0M más que la propuesta de la empresa.';

/** Frames entre palabra y palabra al escribir la respuesta. */
export const WORD_GAP = 2.5;
