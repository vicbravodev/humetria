import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.nom-035.angie.json';
import script from './script.json';
import { GuidesScene, InvitesScene, ReportScene, SampleScene } from './scenes';

export const nom035: Presentation = {
    id: 'NOM035',
    title: 'NOM-035 en cuatro pasos',
    goal: 'RH ve cómo la plataforma decide las guías, sortea la muestra, recuerda a quien falta sin ver respuestas y arma el informe.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'guides', lineId: 'guides', evidence: ['docs/normas/nom-035/criterios.md (num. 4.4: guía por centro y tamaño)', 'docs/design-system/pantallas/capturas/NOM035.png'], component: GuidesScene },
        { id: 'sample', lineId: 'sample', evidence: ['CLAUDE.md#Reglas de dominio NOM-035 (n = 0.9604·N / (0.0025·(N−1) + 0.9604); N=60 → 52)'], component: SampleScene },
        { id: 'invites', lineId: 'invites', evidence: ['docs/design-system/pantallas/capturas/Eval-Activa.png («Aquí ves cuántas personas contestaron, nunca qué contestaron»)'], component: InvitesScene },
        { id: 'report', lineId: 'report', evidence: ['CLAUDE.md#Reglas de dominio NOM-035 (informe num. 7.7; Guía I solo roles autorizados)'], component: ReportScene },
    ],
};

export const nom035Timeline = prepare(nom035);

export const Nom035Film = ({ theme }: { theme: ThemeName }) => <FilmView p={nom035} timeline={nom035Timeline} theme={theme} />;
