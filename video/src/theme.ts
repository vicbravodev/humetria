// Tokens del design system (tema oscuro). Fuente: docs/design-system.
export const c = {
    bg: '#0F1716',
    card: '#141E1C',
    muted: '#1A2523',
    secondary: '#1E2B28',
    accent: '#213330',
    border: '#26332F',
    fg: '#F1FAF8',
    mutedFg: '#9DAEAA',
    primary: '#3CCFBD',
    primaryFg: '#0F1716',
    track: '#26332F',
} as const;

export const risk = {
    null: { solid: '#6FB5A9', bg: '#16302B', fg: '#A6E6DA' },
    low: { solid: '#3CBFAE', bg: '#123A34', fg: '#6FDCCB' },
    medium: { solid: '#D2AE62', bg: '#33291A', fg: '#E9C886' },
} as const;

export type Risk = keyof typeof risk;

export const W = 1280;
export const H = 960;
export const FPS = 30;

// Línea de tiempo (frames, 30 fps). El último estado es idéntico al frame 0.
export const T = {
    s1: 0,
    s2: 200,
    s3: 440,
    s4: 680,
    s5: 920,
    back: 1140, // vuelta al estado inicial
    end: 1180,
} as const;

// Tarjeta principal: la misma geometría abre y cierra el loop.
export const CARD = { x: 96, y: 132, w: 1088, h: 720 } as const;
export const CARD_PAD = 32;
export const HEADER_H = 112;
export const COLHEAD_H = 56;
export const ROW_H = 84;

export const radius = { card: 14, control: 8 } as const;
export const floatShadow = '0 8px 24px rgba(0,0,0,.32)';
