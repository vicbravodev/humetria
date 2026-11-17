import RoomProgress from '@/components/demos/room-progress';
import Notice from '@/components/humetria/notice';
import type { DemoRoom } from '@/types';

/** Under the title: the run going on, the last one that failed, or a demo too old to present. */
export default function RoomNotices({
    run,
    prepared,
}: Pick<DemoRoom, 'run' | 'prepared'>) {
    if (run?.status === 'running') {
        return <RoomProgress step={run.step} />;
    }

    if (run?.status === 'failed') {
        return (
            <Notice>
                <strong>La última preparación falló</strong>
                {` en «${run.step ?? 'el inicio'}» (${run.error_class ?? 'error desconocido'}). Revisa el registro de la aplicación y vuelve a intentarlo.`}
            </Notice>
        );
    }

    if (prepared?.stale === true) {
        return (
            <Notice>
                <strong>{`La demo se preparó ${prepared.ago}.`}</strong> Sus
                datos ya envejecieron: reiníciala antes de presentar.
            </Notice>
        );
    }

    return null;
}
