import { Lock } from 'lucide-react';

/** Above the names of who needs care (pantallas: Canalizacion): who may see them, and that looking leaves a record. */
export default function ConfidentialBanner() {
    return (
        <div className="flex flex-wrap items-center gap-3 rounded-[10px] border bg-card px-4 py-3">
            <Lock aria-hidden className="size-[18px] shrink-0 text-primary" />
            <p className="min-w-0 flex-[1_1_300px] text-sm">
                <strong className="font-semibold">
                    Información confidencial.
                </strong>{' '}
                Solo la ven las personas autorizadas de tu empresa. No la
                compartas fuera de quienes atienden.
            </p>
            <p className="text-[13px] leading-[18px] text-muted-foreground">
                Tu consulta queda registrada
            </p>
        </div>
    );
}
