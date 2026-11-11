import type { ComponentType } from 'react';
import { Sequence, useCurrentFrame } from 'remotion';
import { enter } from '../ease';
import { Logo, Narration, SceneFrame, Stage, Subtitle } from './primitives';
import type { Presentation, SceneProps } from './schema';
import type { PlacedScene, } from './timeline';
import { layout } from './timeline';
import { useTheme, type ThemeName } from './themes';
import { dur } from './tokens';
import { validate } from './schema';

/** Valida y coloca una presentación; lanza si es inválida (el estudio lo muestra al abrirla). */
export const prepare = (p: Presentation): ReturnType<typeof layout> => {
    const errors = validate(p);
    if (errors.length > 0) {
        throw new Error(errors.join('\n'));
    }
    return layout(p);
};

const ChapterHeader = ({ f, tool, label, n }: { f: number; tool: string; label: string; n: number }) => {
    const c = useTheme();
    return (
        <>
            <div style={{ position: 'absolute', left: 80, top: 44, height: 56, display: 'flex', alignItems: 'center', gap: 16, fontSize: 34, fontWeight: 600, ...enter(f, 2, { dur: dur.enter, y: 0, scale: 1, blur: 0 }) }}>
                <span style={{ width: 48, height: 48, borderRadius: 10, background: c.accent, color: c.primary, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 28 }}>{n}</span>
                {label}
                <span style={{ fontWeight: 400, color: c.mutedFg, fontSize: 28 }}>· {tool}</span>
            </div>
            <Logo height={40} style={{ position: 'absolute', right: 80, top: 52 }} />
        </>
    );
};

const SceneInner = ({ p, s, text, Scene }: { p: Presentation; s: PlacedScene; text: string; Scene: ComponentType<SceneProps> }) => {
    const f = useCurrentFrame();
    return (
        <SceneFrame f={f} total={s.dur} last={s.index === p.scenes.length - 1}>
            {p.frame ? <ChapterHeader f={f} tool={p.frame.tool} label={p.frame.chapters[s.index] ?? ''} n={s.index + 1} /> : null}
            <Scene f={f} dur={s.dur} audioAt={s.audioAt} audioDur={s.audioDur} />
            <Subtitle f={f} text={text} audioAt={s.audioAt} audioDur={s.audioDur} />
        </SceneFrame>
    );
};

export const FilmView = ({ p, timeline, theme }: { p: Presentation; timeline: ReturnType<typeof layout>; theme: ThemeName }) => (
    <Stage theme={theme}>
        {timeline.scenes.map((s) => {
            const spec = p.scenes[s.index];
            const line = p.lines.find((l) => l.id === spec?.lineId);
            if (!spec || !line) {
                return null;
            }
            return (
                <Sequence key={s.id} from={s.from} durationInFrames={s.dur} layout="none">
                    <SceneInner p={p} s={s} text={line.text} Scene={spec.component} />
                </Sequence>
            );
        })}
        <Narration scenes={timeline.scenes} />
    </Stage>
);
