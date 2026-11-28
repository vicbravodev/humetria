import { Square } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { end } from '@/routes/demo-room/session';

/** «Terminar sesión», asked first: the reset wipes everything the audience left, the people added live too. */
export default function RoomSessionEnd({
    client,
    variant = 'outline',
}: {
    client: string | null;
    variant?: 'outline' | 'default';
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button variant={variant} className="max-sm:w-full">
                    <Square aria-hidden />
                    Terminar sesión
                </Button>
            }
            form={end.form()}
            preserveScroll
            title={
                client === null
                    ? '¿Terminar la sesión?'
                    : `¿Terminar la sesión con ${client}?`
            }
            description="Se borra todo, incluidas las personas que diste de alta: sus correos, teléfonos, respuestas, marcas y llaves. La demo vuelve a su historia de siempre."
            confirmLabel="Terminar y borrar"
        />
    );
}
