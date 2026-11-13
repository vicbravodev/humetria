import Notice from '@/components/humetria/notice';

/** What the person reads when their answers could not go out for lack of signal: nothing is lost, and it goes by itself. */
export default function SurveyOffline() {
    return (
        <Notice>
            <strong>Sin conexión:</strong> se enviará sola cuando vuelva la
            señal. Sus respuestas siguen en este teléfono.
        </Notice>
    );
}
