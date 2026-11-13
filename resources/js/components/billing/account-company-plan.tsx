import { Badge } from '@/components/ui/badge';
import { TableCell } from '@/components/ui/table';
import type { SubscriptionView } from '@/types';

/** The cells of a company's row that come from its plan: what it contracted, what it costs and when it renews. */
export default function AccountCompanyPlan({
    subscription,
    people,
    workCenters,
}: {
    subscription: SubscriptionView | null;
    people: number;
    workCenters: number;
}) {
    if (subscription === null) {
        return (
            <>
                <TableCell className="text-sm text-muted-foreground">
                    Sin plan asignado
                </TableCell>
                <TableCell className="text-sm text-muted-foreground tabular-nums">
                    {people} {people === 1 ? 'persona' : 'personas'}
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">
                    —
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">
                    —
                </TableCell>
            </>
        );
    }

    const { quote } = subscription;

    return (
        <>
            <TableCell className="whitespace-normal">
                <div className="flex flex-col items-start gap-1">
                    <span className="font-medium">
                        {subscription.plan_label}
                    </span>
                    <span className="text-caption text-muted-foreground">
                        {subscription.profile_label}
                    </span>
                    <Badge variant="outline">{subscription.status_label}</Badge>
                    {subscription.scheduled !== null && (
                        <span
                            data-test="plan-scheduled"
                            className="text-caption text-muted-foreground"
                        >
                            El {subscription.scheduled.effective_on}:{' '}
                            {subscription.scheduled.summary}
                        </span>
                    )}
                </div>
            </TableCell>
            <TableCell className="text-sm whitespace-normal tabular-nums">
                <div className="flex flex-col">
                    <span>
                        {people} de {subscription.seats} personas
                    </span>
                    <span className="text-caption text-muted-foreground">
                        {workCenters} de {subscription.allowed_centers} centros
                    </span>
                </div>
            </TableCell>
            <TableCell className="text-sm whitespace-normal tabular-nums">
                {quote.requires_quotation ? (
                    <span className="text-muted-foreground">
                        Requiere cotización
                    </span>
                ) : (
                    <div className="flex flex-col">
                        <span className="font-medium">{quote.subtotal}</span>
                        <span className="text-caption text-muted-foreground">
                            {quote.total} con IVA
                        </span>
                    </div>
                )}
            </TableCell>
            <TableCell className="text-sm tabular-nums">
                {subscription.renews_on}
            </TableCell>
        </>
    );
}
