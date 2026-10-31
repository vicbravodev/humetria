import { usePage } from '@inertiajs/react';
import { AlertCircleIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/** Why the last change was refused (already published, no open draft), whichever button or form sent it. */
export default function ChecklistError() {
    const message = usePage().props.errors['checklist'];

    if (message === undefined) {
        return null;
    }

    return (
        <Alert variant="destructive" role="alert">
            <AlertCircleIcon aria-hidden />
            <AlertTitle>No se pudo guardar el cambio</AlertTitle>
            <AlertDescription>{message}</AlertDescription>
        </Alert>
    );
}
