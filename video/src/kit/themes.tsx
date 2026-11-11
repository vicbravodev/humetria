import { createContext, useContext, type ReactNode } from 'react';
import { c as dark } from '../theme';

export type ThemeName = 'dark' | 'light';

export type Palette = {
    bg: string;
    card: string;
    muted: string;
    secondary: string;
    accent: string;
    border: string;
    fg: string;
    mutedFg: string;
    primary: string;
    primaryFg: string;
    track: string;
    /** Fondo de los subtítulos. */
    scrim: string;
    /** Logo horizontal de la marca para este fondo (en public/). */
    logo: string;
};

// Oscuro: tokens de ../theme. Claro: los de resources/css/app.css (:root), los mismos que usa video/hyperframes/render.sh.
export const palettes: Record<ThemeName, Palette> = {
    dark: { ...dark, scrim: 'rgba(15,23,22,.82)', logo: 'brand/humetria-horizontal-color-oscuro.svg' },
    light: {
        bg: '#fbfdfc',
        card: '#ffffff',
        muted: '#f1f5f4',
        secondary: '#eef3f2',
        accent: '#e5f1ee',
        border: '#dce5e2',
        fg: '#14211f',
        mutedFg: '#56635f',
        primary: '#0b7a6f',
        primaryFg: '#ffffff',
        track: '#dce5e2',
        scrim: 'rgba(251,253,252,.9)',
        logo: 'brand/humetria-horizontal-color.svg',
    },
};

const Ctx = createContext<Palette>(palettes.dark);

export const ThemeProvider = ({ theme, children }: { theme: ThemeName; children: ReactNode }) => (
    <Ctx.Provider value={palettes[theme]}>{children}</Ctx.Provider>
);

export const useTheme = (): Palette => useContext(Ctx);
