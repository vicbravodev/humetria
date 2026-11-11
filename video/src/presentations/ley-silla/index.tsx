import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.ley-silla.angie.json';
import script from './script.json';
import { PlanScene, PositionsScene, RegulationScene, SurveyScene } from './scenes';

export const leySilla: Presentation = {
    id: 'LeySilla',
    title: 'Ley Silla en cuatro pasos',
    goal: 'RH ve cómo se registra un puesto de pie y dónde va su asiento, cómo se pregunta al personal de forma confidencial, cómo se propone el reglamento interior y cómo se sigue el plan con evidencia.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'positions', lineId: 'positions', evidence: ['docs/normas/ley-silla/criterios.md C-05, C-06 (Figura 1: A, B o C), C-01 (asientos contra personas por turno)', 'resources/js/components/seating/figure-one-result.tsx'], component: PositionsScene },
        { id: 'survey', lineId: 'survey', evidence: ['docs/normas/ley-silla/criterios.md C-19 (encuesta confidencial, enlace de un solo uso)', 'app/Modules/Instruments/Support/Seating/SeatingSurvey.php (SS-02 literal)', 'CLAUDE.md#Privacidad (mínimo por grupo)'], component: SurveyScene },
        { id: 'regulation', lineId: 'regulation', evidence: ['docs/normas/ley-silla/criterios.md C-13 (tres cláusulas; aviso: no es asesoría legal, lo revisa el abogado y aprueba la comisión mixta)'], component: RegulationScene },
        { id: 'plan', lineId: 'plan', evidence: ['docs/normas/ley-silla/criterios.md C-10 (medidas con estado y evidencia)', 'resources/js/lib/seating/plan.ts (Planeada, En curso, Hecha; regla R7)'], component: PlanScene },
    ],
};

export const leySillaTimeline = prepare(leySilla);

export const LeySillaFilm = ({ theme, probe }: { theme: ThemeName; probe?: boolean | undefined }) => <FilmView p={leySilla} timeline={leySillaTimeline} theme={theme} probe={probe} />;
