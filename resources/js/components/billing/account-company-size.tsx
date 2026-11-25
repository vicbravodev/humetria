import { TableCell } from '@/components/ui/table';
import { countOf } from '@/lib/plural';
import type { SubscriptionView } from '@/types';

/** What a company's plan covers against what it has: people and centers, or for a union, its members and that its fee counts none. */
export default function AccountCompanySize({
    subscription,
    people,
    workCenters,
    union,
}: {
    subscription: SubscriptionView;
    people: number;
    workCenters: number;
    /** A union has no work centers, and its fee does not count its members. */
    union: boolean;
}) {
    return (
        <TableCell className="text-sm whitespace-normal tabular-nums">
            {union ? (
                <div className="flex flex-col">
                    <span>
                        {people} {people === 1 ? 'agremiado' : 'agremiados'}
                    </span>
                    <span className="text-caption text-muted-foreground">
                        Cuota fija: no cuenta personas
                    </span>
                </div>
            ) : (
                <div className="flex flex-col">
                    <span>
                        {people} de{' '}
                        {countOf(subscription.seats, 'persona', 'personas')}
                    </span>
                    <span className="text-caption text-muted-foreground">
                        {workCenters} de{' '}
                        {countOf(
                            subscription.allowed_centers,
                            'centro',
                            'centros',
                        )}
                    </span>
                </div>
            )}
        </TableCell>
    );
}
