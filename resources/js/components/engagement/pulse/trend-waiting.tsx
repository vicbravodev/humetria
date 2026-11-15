import { Link } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { index } from '@/routes/tenant/pulse';

/**
 * The trend before it can compare: with no closed survey, what makes it appear; with one, when the next pulse goes out
 * and the way to it.
 */
export default function TrendWaiting({
    waves,
    nextPulseOn,
    canManage,
}: {
    waves: number;
    nextPulseOn: string | null;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const next =
        nextPulseOn === null
            ? null
            : (describeDate(nextPulseOn) ?? nextPulseOn);
    const label =
        next === null ? 'Programa tu primer pulso' : 'Ver el pulso programado';

    return (
        <EmptyState
            icon={Activity}
            title={
                waves === 0
                    ? 'Todavía no hay con qué comparar'
                    : 'Con el siguiente pulso verás aquí la tendencia'
            }
            {...(canManage || next !== null
                ? {
                      action: (
                          <Button asChild>
                              <Link href={index(tenantSlug)}>{label}</Link>
                          </Button>
                      ),
                  }
                : {})}
        >
            {next !== null
                ? `El siguiente sale el ${next}.`
                : 'La tendencia aparece cuando se cierra la primera encuesta, y se vuelve útil desde la segunda.'}
        </EmptyState>
    );
}
