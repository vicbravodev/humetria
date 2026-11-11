import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.org-chart.angie.json';
import script from './script.json';
import { ChartScene, ImportScene, LeaveScene, PortalScene } from './scenes';

export const orgChart: Presentation = {
    id: 'OrgChart',
    title: 'Organigrama en cuatro pasos',
    goal: 'RH dibuja el organigrama a partir de quién reporta a quién, carga la línea de reporte desde su archivo, reasigna al equipo al dar de baja a un jefe y cada jefe ve solo a su gente y aprueba.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'chart', lineId: 'chart', evidence: ['docs/superpowers/specs/2026-11-09-vacations-orgchart-pulse-climate-design.md (línea de reporte, organigrama de N niveles)', 'resources/js/components/organization/org-tree-node.tsx'], component: ChartScene },
        { id: 'import', lineId: 'import', evidence: ['resources/js/components/organization/import-instructions.tsx (columna jefe: número de empleado o correo)'], component: ImportScene },
        { id: 'leave', lineId: 'leave', evidence: ['app/Modules/Organization/Http/Requests/DeactivateEmployeeRequest.php (reassign_to)', 'app/Modules/Organization/Enums/ReportingIssue.php («Jefe dado de baja»)'], component: LeaveScene },
        { id: 'portal', lineId: 'portal', evidence: ['resources/js/pages/organization/portal-team.tsx («Mi equipo», «Su jefe directo»)', 'docs/superpowers/specs/2026-11-09-vacations-orgchart-pulse-climate-design.md (el jefe aprueba desde su portal)'], component: PortalScene },
    ],
};

export const orgChartTimeline = prepare(orgChart);

export const OrgChartFilm = ({ theme }: { theme: ThemeName }) => <FilmView p={orgChart} timeline={orgChartTimeline} theme={theme} />;
