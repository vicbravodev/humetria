import { Badge } from '@/components/ui/badge';
import type { DemoRoom } from '@/types';

/** Where the demo stands, next to the room's title: being prepared, failed, ready or not there yet. */
export default function RoomStatus({
    run,
    company,
}: Pick<DemoRoom, 'run' | 'company'>) {
    if (run?.status === 'running') {
        return <Badge>Preparando</Badge>;
    }

    if (run?.status === 'failed') {
        return <Badge variant="destructive">Falló</Badge>;
    }

    return company === null ? (
        <Badge variant="outline">Sin preparar</Badge>
    ) : (
        <Badge variant="success">Preparada</Badge>
    );
}
