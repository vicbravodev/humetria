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

export const radius = { card: 14, control: 8 } as const;
export const floatShadow = '0 8px 24px rgba(0,0,0,.32)';
