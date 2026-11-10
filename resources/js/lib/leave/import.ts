import { countOf } from '@/lib/plural';
import type { LeaveImportSummary } from '@/types';

/** Where the upload stands: 1 choose the file, 2 review what stays out, 3 confirm a file with something to load. */
export function importStep(
    summary: Pick<LeaveImportSummary, 'ready' | 'rejected'> | undefined,
): 1 | 2 | 3 {
    if (summary === undefined) {
        return 1;
    }

    return summary.ready > 0 ? 3 : 2;
}

/** "Listos para cargar 3 periodos; 2 filas se quedan fuera." */
export function importSummaryText(
    summary: Pick<LeaveImportSummary, 'ready' | 'rejected'>,
): string {
    const ready = `Se cargarán ${countOf(summary.ready, 'periodo', 'periodos')}`;

    return summary.rejected === 0
        ? `${ready}.`
        : `${ready}; ${countOf(summary.rejected, 'fila se queda', 'filas se quedan')} fuera.`;
}

/** The file if one was chosen: an empty file counts as none. */
export function chosenFile(file: File | null | undefined): File | null {
    return file !== undefined && file !== null && file.size > 0 ? file : null;
}
