import { FileSpreadsheet } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** Before a center is chosen: what the report holds and the next step. */
export default function ReportStart() {
    return (
        <EmptyState icon={FileSpreadsheet} title="Elige un centro de trabajo">
            El reporte es por centro y periodo: elige el centro arriba y pulsa
            «Ver reporte». Luego lo descargas en CSV para entregarlo.
        </EmptyState>
    );
}
