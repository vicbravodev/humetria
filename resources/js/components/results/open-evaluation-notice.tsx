import { Alert, AlertDescription } from '@/components/ui/alert';

/** Why an open evaluation shows no Guía II or III results yet. Nothing once it is closed, or with no such guide. */
export default function OpenEvaluationNotice({
    guide,
    closed,
}: {
    guide: { title: string } | null;
    closed: boolean;
}) {
    if (guide === null || closed) {
        return null;
    }

    return (
        <Alert>
            <AlertDescription>
                Los resultados de la {guide.title} se muestran al cerrar la
                evaluación: mientras sigue abierta, cada respuesta nueva diría
                qué contestó quien acaba de contestar.
            </AlertDescription>
        </Alert>
    );
}
