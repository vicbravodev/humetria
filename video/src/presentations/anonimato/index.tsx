import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
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
        { id: 'close', lineId: 'close', evidence: ['CLAUDE.md#Privacidad (resumen)'], camera: false, component: CloseScene },
    ],
};

export const anonimatoTimeline = prepare(anonimato);

export const AnonimatoFilm = ({ theme }: { theme: ThemeName }) => <FilmView p={anonimato} timeline={anonimatoTimeline} theme={theme} />;
