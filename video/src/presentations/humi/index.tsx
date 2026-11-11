import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.humi.angie.json';
import script from './script.json';
import { AnswerScene, AskScene, LimitsScene, ScenarioScene } from './scenes';

export const humi: Presentation = {
    id: 'Humi',
    title: 'Humi, el asistente del panel',
    goal: 'RH le pregunta a Humi con sus palabras; consulta avance y resultados agrupados, cita sus fuentes, explica escenarios de Compensación y solo lee: no cambia datos ni ve respuestas individuales.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'ask', lineId: 'ask', evidence: ['app/Modules/Assistant/README.md (asistente conversacional en el panel de RH)'], component: AskScene },
        { id: 'answer', lineId: 'answer', evidence: ['app/Modules/Assistant/README.md (herramientas de solo lectura; avance, resultados, pendientes)', 'resources/js/lib/assistant/tool-steps.ts («Consultando…» / «Consulté…»)'], component: AnswerScene },
        { id: 'scenarios', lineId: 'scenarios', evidence: ['resources/js/components/landing/humi-section.tsx (escenarios de Compensación y botón a la pantalla)'], component: ScenarioScene },
        { id: 'limits', lineId: 'limits', evidence: ['app/Modules/Assistant/README.md#Reglas que no se rompen (solo lee; nunca respuestas individuales ni la Guía I; empresa primero; se apaga por empresa)'], component: LimitsScene },
    ],
};

export const humiTimeline = prepare(humi);

export const HumiFilm = ({ theme, probe }: { theme: ThemeName; probe?: boolean | undefined }) => <FilmView p={humi} timeline={humiTimeline} theme={theme} probe={probe} />;
