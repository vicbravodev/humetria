import { RefreshCw, Sparkles } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { prepare, reset } from '@/routes/demo-room';

/**
 * «Preparar demo» or «Reiniciar demo», asked first: either one tells the whole story again, and a reset also wipes what
 * the audience left. Disabled while a run is going; the server refuses a second one anyway.
 */
export default function RoomRunButton({
    kind,
    running,
}: {
    kind: 'prepare' | 'reset';
    running: boolean;
}) {
    const first = kind === 'prepare';

    return (
        <ConfirmDialog
            trigger={
                <Button
                    disabled={running}
                    variant={first ? 'default' : 'outline'}
                >
                    {first ? (
                        <Sparkles aria-hidden />
                    ) : (
                        <RefreshCw aria-hidden />
                    )}
                    {first ? 'Preparar demo' : 'Reiniciar demo'}
                </Button>
            }
            form={first ? prepare.form() : reset.form()}
            preserveScroll
            destructive={!first}
            disabled={running}
            title={first ? '¿Preparar la demo?' : '¿Reiniciar la demo?'}
            description={
                first
                    ? 'Se crea Manufacturas Alba del Bajío con su historia en todos los módulos. Tarda alrededor de medio minuto (unos minutos si la cola está ocupada) y corre en segundo plano.'
                    : 'Se borra todo lo de la demo, también lo que el público contestó o registró, y se vuelve a contar la historia a la fecha de hoy. Tarda alrededor de medio minuto (unos minutos si la cola está ocupada). El QR del canal de denuncias sigue sirviendo. La puerta se cierra: ábrela otra vez; los enlaces que dio dejan de servir. La pantalla del checador necesita un enlace nuevo.'
            }
            confirmLabel={first ? 'Preparar' : 'Reiniciar'}
        />
    );
}
