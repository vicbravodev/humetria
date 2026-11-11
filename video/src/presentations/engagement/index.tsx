import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.engagement.angie.json';
import script from './script.json';
import { CompareScene, HiddenScene, PulseScene, SurveyScene } from './scenes';

export const engagement: Presentation = {
    id: 'Engagement',
    title: 'Clima y pulso en cuatro pasos',
    goal: 'La persona contesta la encuesta de clima por su enlace, los pulsos cortos dicen si las acciones funcionan, RH ve qué atender primero con los grupos pequeños ocultos y compara olas y centros sin ver respuestas individuales.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'survey', lineId: 'survey', evidence: ['app/Modules/Engagement/README.md (qué se guarda y qué no de cada respuesta; la invitación solo guarda «ya contestó»)', 'docs/superpowers/specs/2026-11-09-engagement-item-bank.md (escala de 5 puntos; toda pregunta se puede omitir; ítem eng.pride)'], component: SurveyScene },
        { id: 'pulse', lineId: 'pulse', evidence: ['app/Modules/Engagement/README.md#Pulso (mensual o trimestral; 5 fijas y hasta 6 rotativas; modalidades seguimiento, recorrido, jefe; aviso 3 días antes)'], component: PulseScene },
        { id: 'hidden', lineId: 'hidden', evidence: ['app/Modules/Engagement/README.md (mínimo 5 por grupo; nunca se muestra el conteo exacto de un grupo oculto)', 'resources/js/components/engagement/results/priority-list.tsx'], component: HiddenScene },
        { id: 'compare', lineId: 'compare', evidence: ['app/Modules/Engagement/README.md#Pulso (tendencia entre olas; flechas solo con 30 respuestas o más)', 'resources/js/components/engagement/results/cut-picker.tsx'], component: CompareScene },
    ],
};

export const engagementTimeline = prepare(engagement);

export const EngagementFilm = ({ theme, probe }: { theme: ThemeName; probe?: boolean | undefined }) => <FilmView p={engagement} timeline={engagementTimeline} theme={theme} probe={probe} />;
