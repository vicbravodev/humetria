import Notice from '@/components/humetria/notice';
import type { DemoRunState } from '@/types';

/** The last run failed: where and why; for a session's preparation, also that ending the session leaves the demo clean. */
export default function RoomRunFailed({
    run,
    session,
}: {
    run: DemoRunState;
    session: boolean;
}) {
    const where = `«${run.step ?? 'el inicio'}» (${run.error_class ?? 'error desconocido'})`;

    return session ? (
        <Notice>
            <strong>{`La preparación falló en ${where}.`}</strong> Termina la
            sesión para dejar la demo limpia y empieza otra; si vuelve a fallar,
            busca demo.run_failed en los logs.
        </Notice>
    ) : (
        <Notice>
            <strong>La última preparación falló</strong>
            {` en ${where}. Revisa el registro de la aplicación y vuelve a intentarlo.`}
        </Notice>
    );
}
