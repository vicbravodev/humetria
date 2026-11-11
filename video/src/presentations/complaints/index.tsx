import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.complaints.angie.json';
import script from './script.json';
import { FollowScene, NoticeScene, RouteScene, ScanScene } from './scenes';

export const complaints: Presentation = {
    id: 'Complaints',
    title: 'Buzón de quejas en cuatro pasos',
    goal: 'La persona presenta su queja anónima desde el celular, llega solo a quien le toca, los avisos no llevan contenido y el caso se sigue con una referencia.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'scan', lineId: 'scan', evidence: ['resources/js/components/complaints/poster-sheet.tsx (cartel con QR)', 'resources/js/components/complaints-public/step-identity.tsx (Anónima / Confidencial)', 'docs/superpowers/specs/2026-11-03-nom-035-complaints-channel-design.md (referencia y clave una sola vez)'], component: ScanScene },
        { id: 'route', lineId: 'route', evidence: ['docs/superpowers/specs/2026-11-03-nom-035-complaints-channel-design.md (enrutamiento por conflicto de interés)'], component: RouteScene },
        { id: 'notice', lineId: 'notice', evidence: ['docs/superpowers/specs/2026-11-03-nom-035-complaints-channel-design.md (avisos: conteos, sin contenido)', 'resources/js/components/landing/landing-tools.ts (los avisos por correo nunca llevan el contenido)'], component: NoticeScene },
        { id: 'follow', lineId: 'follow', evidence: ['resources/js/components/complaints/case-steps.tsx (Recibida, Acuse enviado, En revisión, Cerrada)', 'resources/js/components/complaints-public/follow-up-view.tsx (seguimiento con referencia y clave)'], component: FollowScene },
    ],
};

export const complaintsTimeline = prepare(complaints);

export const ComplaintsFilm = ({ theme }: { theme: ThemeName }) => <FilmView p={complaints} timeline={complaintsTimeline} theme={theme} />;
