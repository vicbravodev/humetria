import './fonts';
import { AbsoluteFill, Img, staticFile, useCurrentFrame } from 'remotion';
import { easeInOut, enter, exit, tween } from './ease';
import { Confidential } from './scenes/Confidential';
import { Dossier } from './scenes/Dossier';
import { Invite } from './scenes/Invite';
import { LoadPeople } from './scenes/LoadPeople';
import { Results } from './scenes/Results';
import { T, c } from './theme';

const SCENES = [
    { n: 1, label: 'Carga a tu gente', from: T.s1, to: T.s2 },
    { n: 2, label: 'Invita y recuerda', from: T.s2, to: T.s3 },
    { n: 3, label: 'Confidencial por diseño', from: T.s3, to: T.s4 },
    { n: 4, label: 'Resultados y plan', from: T.s4, to: T.s5 },
    { n: 5, label: 'Lista para la STPS', from: T.s5, to: T.back },
] as const;

// El rótulo 1 vuelve al cerrar el loop; en el frame 0 ya está completo.
const CAPTIONS = [
    ...SCENES.map((s) => ({ n: s.n, label: s.label, at: s.from === 0 ? -1000 : s.from })),
    { n: 1, label: 'Carga a tu gente', at: T.back },
];

const Caption = ({ f }: { f: number }) => (
    <>
        {CAPTIONS.map((cap, i) => {
            const next = CAPTIONS[i + 1];
            if (f < cap.at - 2 || (next && f > next.at + 14)) {
                return null;
            }
            const style = next && f >= next.at ? exit(f, next.at, { dur: 10, y: -10, scale: 1 }) : enter(f, cap.at + 6, { dur: 18, y: 10, scale: 1 });
            return (
                <div
                    key={`${cap.n}-${cap.at}`}
                    style={{
                        position: 'absolute',
                        left: 96,
                        top: 52,
                        height: 50,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 14,
                        fontSize: 32,
                        fontWeight: 600,
                        color: c.fg,
                        whiteSpace: 'nowrap',
                        ...style,
                    }}
                >
                    <span
                        style={{
                            width: 40,
                            height: 40,
                            borderRadius: 8,
                            background: c.accent,
                            color: c.primary,
                            fontSize: 26,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        {cap.n}
                    </span>
                    {cap.label}
                </div>
            );
        })}
    </>
);

const Rail = ({ f }: { f: number }) => {
    const reset = 1 - tween(f, T.back, 20, easeInOut);
    return (
        <div style={{ position: 'absolute', left: 96, right: 96, top: 894, height: 4, display: 'flex', gap: 10 }}>
            {SCENES.map((s) => (
                <div key={s.n} style={{ flex: 1, borderRadius: 99, background: c.track, overflow: 'hidden' }}>
                    <div
                        style={{
                            width: '100%',
                            height: '100%',
                            background: c.primary,
                            transformOrigin: 'left',
                            transform: `scaleX(${tween(f, s.from, s.to - s.from, (t) => t)})`,
                            opacity: reset,
                        }}
                    />
                </div>
            ))}
        </div>
    );
};

export const HeroLoop = () => {
    const f = useCurrentFrame();
    // Respiración lenta de la cámara: 1 → 1.012 → 1 a lo largo del loop.
    const drift = 1 + 0.012 * ((1 - Math.cos((2 * Math.PI * f) / T.end)) / 2);
    return (
        <AbsoluteFill style={{ background: c.bg, fontFamily: 'Figtree, sans-serif', fontVariantNumeric: 'tabular-nums', color: c.fg }}>
            <AbsoluteFill style={{ transform: `scale(${drift})` }}>
                <LoadPeople />
                <Invite />
                <Confidential />
                <Results />
                <Dossier />
            </AbsoluteFill>
            <Caption f={f} />
            <Img
                src={staticFile('brand/humetria-horizontal-color-oscuro.svg')}
                style={{ position: 'absolute', right: 96, top: 60, height: 34 }}
            />
            <Rail f={f} />
        </AbsoluteFill>
    );
};
