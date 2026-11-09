import { CalendarRange, FileDown } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';

/** A period too long for the screen: why there is no table, and its CSV to download. */
export default function ReportLongPeriod({
    days,
    maxOnScreen,
    exportUrl,
}: {
    days: number;
    maxOnScreen: number;
    exportUrl: string;
}) {
    return (
        <EmptyState
            icon={CalendarRange}
            title="Este periodo se descarga en CSV"
            action={
                <Button asChild>
                    <a href={exportUrl} download>
                        <FileDown aria-hidden />
                        Descargar CSV
                    </a>
                </Button>
            }
        >
            {`Elegiste ${days} días y en pantalla mostramos hasta ${maxOnScreen}. Descarga el CSV con cada día de cada persona del periodo, o elige uno más corto para verlo aquí.`}
        </EmptyState>
    );
}
