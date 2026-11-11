import type { ReactNode } from 'react';
import { AbsoluteFill, Audio, Img, Sequence, staticFile } from 'remotion';
import '../fonts';
import { easeInOut, enter, tween } from '../ease';
import type { PlacedScene } from './timeline';
import { c, dur, radius } from './tokens';

/** Fondo, tipografía y números tabulares de toda pieza. */
export const Stage = ({ children }: { children: ReactNode }) => (
    <AbsoluteFill style={{ background: c.bg, fontFamily: 'Figtree, sans-serif', fontVariantNumeric: 'tabular-nums', color: c.fg }}>
        {children}
    </AbsoluteFill>
);

/** Envuelve una escena: entra y sale solo con fundido (sin desplazar contenedores). La última escena no sale: se queda. */
export const SceneFrame = ({ f, total, last, children }: { f: number; total: number; last?: boolean; children: ReactNode }) => {
    const out = last === true ? 0 : tween(f, total - dur.scene, dur.scene, easeInOut);
    return <AbsoluteFill style={{ opacity: (f < dur.scene ? tween(f, 0, dur.scene) : 1) * (1 - out) }}>{children}</AbsoluteFill>;
};

/** Un elemento que entra en `at` con la entrada estándar del kit. */
export const Reveal = ({ f, at, children, y, x, calm, style }: { f: number; at: number; children: ReactNode; y?: number; x?: number; calm?: boolean; style?: React.CSSProperties }) => (
    <div
        style={{
            // `calm`: solo fundido, sin escala, desplazamiento ni desenfoque (logos y texto final: nada debe vibrar).
            ...enter(f, at, calm ? { dur: dur.scene, y: 0, x: 0, scale: 1, blur: 0 } : { dur: dur.enter, ...(y !== undefined ? { y } : {}), ...(x !== undefined ? { x } : {}) }),
            ...style,
        }}
    >
        {children}
    </div>
);

/** Audio de cada línea, en el frame exacto de su escena. */
export const Narration = ({ scenes }: { scenes: readonly PlacedScene[] }) => (
    <>
        {scenes.map((s) => (
            <Sequence key={s.id} from={s.from + s.audioAt} durationInFrames={s.audioDur + 2}>
                <Audio src={staticFile(s.file)} />
            </Sequence>
        ))}
    </>
);

/** Subtítulo de la línea mientras suena. Se queda un instante tras la voz. */
export const Subtitle = ({ f, text, audioAt, audioDur }: { f: number; text: string; audioAt: number; audioDur: number }) => {
    const show = tween(f, audioAt - 2, dur.micro + 4);
    const hide = tween(f, audioAt + audioDur + 4, dur.micro);
    return (
        <div style={{ position: 'absolute', left: 0, right: 0, bottom: 64, display: 'flex', justifyContent: 'center', opacity: show * (1 - hide) }}>
            <div
                style={{
                    maxWidth: 1380,
                    padding: '14px 28px',
                    borderRadius: radius.card,
                    background: 'rgba(15,23,22,.82)',
                    border: `1px solid ${c.border}`,
                    fontSize: 40,
                    lineHeight: 1.3,
                    fontWeight: 500,
                    textAlign: 'center',
                }}
            >
                {text}
            </div>
        </div>
    );
};

export const Logo = ({ height, style }: { height: number; style?: React.CSSProperties }) => (
    <Img src={staticFile('brand/humetria-horizontal-color-oscuro.svg')} style={{ height, ...style }} />
);
