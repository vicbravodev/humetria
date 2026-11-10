import LoopVideo from '@/components/landing/loop-video';

/** The time clock at work, as a film: a mark, a record nobody can alter, the week confirmed, the report. */
export default function VisualAttendance() {
    return (
        <LoopVideo
            name="humetria-attendance"
            test="attendance-video"
            caption="Así se marca la jornada, en 24 segundos"
            description="la persona marca desde su celular dentro de la zona del centro, el registro no se puede alterar y las correcciones se suman con su motivo, confirma su semana con los topes a la vista y RH genera con un botón el reporte para la autoridad."
            className="aspect-[4/3]"
        />
    );
}
