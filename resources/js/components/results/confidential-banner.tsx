import { Lock } from 'lucide-react';

/** Above the names of who needs care (board V4-N035-Canalizacion): who may see them, and that looking leaves a record. */
export default function ConfidentialBanner() {
    return (
        <div className="flex flex-wrap items-center gap-3 rounded-[10px] border bg-card px-4 py-3">
            <Lock aria-hidden className="size-[18px] shrink-0 text-primary" />
            <p className="min-w-0 flex-[1_1_300px] text-sm">
                <strong className="font-semibold">
                    Información confidencial.
                </strong>{' '}
                Solo la ven quienes administran todo en tu empresa. No guardamos
                respuestas ni diagnósticos, solo a dónde se canalizó y cuándo.
            </p>
            <p className="text-caption text-muted-foreground">
                Tu consulta quedó registrada
            </p>
        </div>
    );
}
