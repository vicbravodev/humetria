import { FilmView, prepare } from '../../kit/film';
import type { Presentation } from '../../kit/schema';
import type { ThemeName } from '../../kit/themes';
import { FORMAT_16x9, FPS } from '../../kit/tokens';
import manifest from '../../../voice/manifest.attendance.angie.json';
import script from './script.json';
import { MarkScene, RecordScene, ReportScene, WeekScene } from './scenes';

export const attendance: Presentation = {
    id: 'Attendance',
    title: 'Registro de jornada en cuatro pasos',
    goal: 'La persona marca desde su celular con huella, el registro no se altera y las correcciones se suman con motivo, confirma su semana con los topes a la vista y RH arma el reporte para la autoridad.',
    ...FORMAT_16x9,
    fps: FPS,
    outro: script.outro,
    frame: script.frame,
    lines: script.lines,
    manifest: manifest.lines,
    scenes: [
        { id: 'mark', lineId: 'mark', evidence: ['resources/js/components/landing/landing-tools.ts (marca desde el celular, QR o geocerca, sin correo ni contraseña)', 'resources/js/lib/attendance/next-step.ts («Marcar entrada», «Entrada registrada»)'], component: MarkScene },
        { id: 'record', lineId: 'record', evidence: ['docs/normas/jornada/criterios.md 1.3 y 5 (registro acordado, correcciones con rastro)', 'resources/js/lib/attendance/next-step.ts (Entrada, Salida a comer, Regreso de comer)'], component: RecordScene },
        { id: 'week', lineId: 'week', evidence: ['docs/normas/jornada/criterios.md 2.1 y 2.3 (46 h semanales y 9 h extra en 2027)', 'resources/js/components/attendance/week-hours.tsx (horas ordinarias, extra dobles, triples, día de descanso)', 'resources/js/components/attendance/week-actions.tsx («Confirmar mi semana»)'], component: WeekScene },
        { id: 'report', lineId: 'report', evidence: ['docs/normas/jornada/criterios.md 1.1 y 6 (LFT 132-XXXIV; reporte para la autoridad)', 'resources/js/components/attendance/report-legend.tsx («el silencio no es conformidad»)'], component: ReportScene },
    ],
};

export const attendanceTimeline = prepare(attendance);

export const AttendanceFilm = ({ theme, probe }: { theme: ThemeName; probe?: boolean | undefined }) => <FilmView p={attendance} timeline={attendanceTimeline} theme={theme} probe={probe} />;
