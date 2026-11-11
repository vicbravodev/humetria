import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.nom-037.angie.json';
import script from './script.json';
import { ChecklistScene, PolicyScene, ReviewScene, RosterScene } from './scenes';

export const nom037: Presentation = {
    id: 'NOM037',
    title: 'NOM-037 en cuatro pasos',
    goal: 'RH ve cómo se arma el listado de teletrabajo, cómo cada persona contesta la lista de su lugar, cómo se arma la política y cómo nada vence sin aviso.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'roster', lineId: 'roster', evidence: ['docs/normas/nom-037/criterios.md C-01 (más del 40 %; 40 exacto no, 41 sí) y C-04 (11 datos)'], component: RosterScene },
        { id: 'checklist', lineId: 'checklist', evidence: ['docs/normas/nom-037/criterios.md C-11, C-12 (la persona contesta, la Comisión valida)', 'texto-de-referencia.md S1-04', 'resources/js/lib/telework/review.ts (decisiones)'], component: ChecklistScene },
        { id: 'policy', lineId: 'policy', evidence: ['docs/normas/nom-037/criterios.md C-19, C-20 (8 preguntas, incisos a–l, acuses)', 'app/Modules/Telework/Support/Writing/PolicyTemplate.php (preguntas)'], component: PolicyScene },
        { id: 'review', lineId: 'review', evidence: ['docs/normas/nom-037/criterios.md C-14 (periodicidad 3, 6, 12 o 24 meses; aviso por vencer)'], component: ReviewScene },
    ],
};

export const nom037Timeline = prepare(nom037);

export const Nom037Film = ({ theme, probe }: { theme: ThemeName; probe?: boolean | undefined }) => <FilmView p={nom037} timeline={nom037Timeline} theme={theme} probe={probe} />;
