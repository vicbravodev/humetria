import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.leave.angie.json';
import script from './script.json';
import { ApproveScene, BalanceScene, EvidenceScene, RequestScene } from './scenes';

export const leave: Presentation = {
    id: 'Leave',
    title: 'Vacaciones y permisos en cuatro pasos',
    goal: 'La persona ve su saldo conforme a la ley y a la política, solicita desde su portal, su jefe aprueba y la ausencia aparece en el calendario del equipo, y el expediente guarda la solicitud y la constancia anual.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'balance', lineId: 'balance', evidence: ['docs/normas/vacaciones/criterios.md C-01 (art. 76: 12, 14, 16, 18, 20, 22 días) y C-13 (la ley es el piso)'], component: BalanceScene },
        { id: 'request', lineId: 'request', evidence: ['resources/js/components/leave/request-form.tsx («Enviar solicitud»)', 'docs/normas/vacaciones/criterios.md C-06 (doce días continuos: se avisa, no se bloquea)'], component: RequestScene },
        { id: 'approve', lineId: 'approve', evidence: ['docs/normas/vacaciones/criterios.md C-18 (aprueba el jefe; RH respalda a los tres días hábiles)', 'resources/js/components/leave/decision-card.tsx («Aprobar»), calendar-grid.tsx (calendario del equipo)'], component: ApproveScene },
        { id: 'evidence', lineId: 'evidence', evidence: ['docs/normas/vacaciones/criterios.md C-10 (constancia anual con acuse), C-12 (nada se borra)', 'resources/js/components/leave/statement-sheet.tsx (Persona, Antigüedad, Vacaciones, Disfrute, Prima)'], component: EvidenceScene },
    ],
};

export const leaveTimeline = prepare(leave);

export const LeaveFilm = ({ theme }: { theme: ThemeName }) => <FilmView p={leave} timeline={leaveTimeline} theme={theme} />;
