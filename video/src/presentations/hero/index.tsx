import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.hero.angie.json';
import script from './script.json';
import { DossierScene, GroupsScene, InviteScene, PeopleScene, ToolsScene } from './scenes';

export const hero: Presentation = {
    id: 'Hero',
    title: 'Cómo funciona Humetría',
    goal: 'RH carga a su gente una vez, elige qué herramientas aplicar, cada persona contesta desde su celular, los resultados llegan por grupos sin nombres y todo queda en un expediente.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'people', lineId: 'people', evidence: ['app/Modules/Organization/README.md (plantilla por centro de trabajo y área; carga por archivo)'], component: PeopleScene },
        { id: 'tools', lineId: 'tools', evidence: ['resources/js/components/landing/landing-tools.ts (las nueve herramientas)'], component: ToolsScene },
        { id: 'invite', lineId: 'invite', evidence: ['CLAUDE.md#Privacidad (enlace de un solo uso; la invitación solo registra «ya contestó»)'], component: InviteScene },
        { id: 'groups', lineId: 'groups', evidence: ['CLAUDE.md#Privacidad (mínimo de respondentes por grupo, default 5; RH nunca ve respuestas individuales)'], component: GroupsScene },
        { id: 'dossier', lineId: 'dossier', evidence: ['docs/normas/nom-035/criterios.md (informe, num. 7.7; evidencia conservada)', 'resources/js/components/landing/landing-tools.ts (informes, evidencia y plan por herramienta)'], component: DossierScene },
    ],
};

export const heroTimeline = prepare(hero);

export const HeroFilm = ({ theme, probe }: { theme: ThemeName; probe?: boolean | undefined }) => <FilmView p={hero} timeline={heroTimeline} theme={theme} probe={probe} />;
