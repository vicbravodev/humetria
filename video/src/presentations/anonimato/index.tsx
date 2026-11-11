import { Sequence } from 'remotion';
import { Narration, SceneFrame, Stage, Subtitle } from '../../kit/primitives';
import { validate, type Presentation } from '../../kit/schema';
import { layout } from '../../kit/timeline';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.anonimato.angie.json';
import script from './script.json';
import { CloseScene, GroupsScene, LinkScene, NeedScene, SplitScene } from './scenes';

export const anonimato: Presentation = {
    id: 'Anonimato',
    title: 'Responder con confianza',
    goal: 'Quien responde necesita saber que nadie verá qué contestó; RH necesita datos reales. Muestra el anonimato como diseño, no como promesa.',
    ...FORMAT_16x9,
    fps: FPS,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'need', lineId: 'need', evidence: ['docs/normas/nom-035/texto-de-referencia.md (ítem 1 de la Guía II)'], component: NeedScene },
        { id: 'link', lineId: 'link', evidence: ['CLAUDE.md#Privacidad (enlace de un solo uso)', 'resources/js/components/landing/landing-tools.ts (respuesta desde el celular)'], component: LinkScene },
        { id: 'split', lineId: 'split', evidence: ['CLAUDE.md#Privacidad (tablas sin llave hacia empleado o invitación; la invitación solo registra «ya contestó»)'], component: SplitScene },
        { id: 'groups', lineId: 'groups', evidence: ['CLAUDE.md#Privacidad (mínimo de respondentes por grupo, default 5, configurable)'], component: GroupsScene },
        { id: 'close', lineId: 'close', evidence: ['CLAUDE.md#Privacidad (resumen)'], component: CloseScene },
    ],
};

export const anonimatoTimeline = layout(anonimato);
const errors = validate(anonimato);
if (errors.length > 0) {
    throw new Error(errors.join('\n'));
}

export const AnonimatoFilm = () => (
    <Stage>
        {anonimatoTimeline.scenes.map((s) => {
            const spec = anonimato.scenes[s.index];
            const line = anonimato.lines.find((l) => l.id === spec?.lineId);
            if (!spec || !line) {
                return null;
            }
            const Scene = spec.component;
            return (
                <Sequence key={s.id} from={s.from} durationInFrames={s.dur} layout="none">
                    <SceneInner s={s} text={line.text} Scene={Scene} />
                </Sequence>
            );
        })}
        <Narration scenes={anonimatoTimeline.scenes} />
    </Stage>
);

import { useCurrentFrame } from 'remotion';
import type { ComponentType } from 'react';
import type { SceneProps } from '../../kit/schema';
import type { PlacedScene } from '../../kit/timeline';

const SceneInner = ({ s, text, Scene }: { s: PlacedScene; text: string; Scene: ComponentType<SceneProps> }) => {
    const f = useCurrentFrame();
    return (
        <SceneFrame f={f} total={s.dur} last={s.index === anonimato.scenes.length - 1}>
            <Scene f={f} dur={s.dur} audioAt={s.audioAt} audioDur={s.audioDur} />
            <Subtitle f={f} text={text} audioAt={s.audioAt} audioDur={s.audioDur} />
        </SceneFrame>
    );
};
