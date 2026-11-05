import Notice from '@/components/humetria/notice';

/** The answers did not go out for lack of a network (board V3-Estados, «Portal sin conexión»): nothing was lost. */
export default function PortalOffline() {
    return (
        <Notice>
            <strong>Sin conexión.</strong> Sus respuestas siguen en este
            teléfono y se envían al volver la conexión.
        </Notice>
    );
}
