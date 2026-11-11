import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.compensation.angie.json';
import script from './script.json';
import { CompareScene, CostScene, ProjectScene, ReportScene } from './scenes';

export const compensation: Presentation = {
    id: 'Compensation',
    title: 'Compensación en cuatro pasos',
    goal: 'La persona costea un aumento con todas sus cargas, compara propuestas y la distancia a negociar, proyecta cinco años y comparte el reporte con un enlace de solo lectura. Todas las cifras son de ejemplo.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'cost', lineId: 'cost', evidence: ['app/Modules/Compensation/Support/ConceptLabels.php (salario, prestaciones, IMSS, Infonavit, impuesto sobre nómina)', 'docs/normas/compensation/criterios.md (cuotas y aportaciones)'], component: CostScene },
        { id: 'compare', lineId: 'compare', evidence: ['resources/js/components/compensation/compare-distance.tsx («La distancia a negociar», punto a sindicalizados, mes de retroactivo, «Probar un punto medio»)'], component: CompareScene },
        { id: 'project', lineId: 'project', evidence: ['resources/js/components/compensation/report-years.tsx («Los próximos años», supuestos de plantilla y revisiones futuras)'], component: ProjectScene },
        { id: 'report', lineId: 'report', evidence: ['resources/js/components/compensation/share-dialog.tsx (enlace temporal para alguien sin cuenta)', 'resources/js/components/compensation/revoke-share-dialog.tsx'], component: ReportScene },
    ],
};

export const compensationTimeline = prepare(compensation);

export const CompensationFilm = ({ theme }: { theme: ThemeName }) => <FilmView p={compensation} timeline={compensationTimeline} theme={theme} />;
