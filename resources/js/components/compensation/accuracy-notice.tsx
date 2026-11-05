import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ContractStatus } from '@/lib/compensation/start';
import { accuracySentence } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';

/**
 * The one notice of accuracy (C6), in place of a warning for each thing that is missing: how many people the cost covers, how
 * many data would make it exact and the one action that completes them, in the data of the contract. The report can show it too.
 */
export default function AccuracyNotice({
    accuracy,
    title,
}: {
    accuracy: ContractStatus['accuracy'];
    title: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
            <div className="grid gap-1">
                <h2 className="text-xl leading-7 font-semibold">{title}</h2>
                <p className="text-muted-foreground">
                    {accuracySentence(accuracy)}
                </p>
            </div>
            {accuracy.missing > 0 && (
                <Button variant="outline" asChild>
                    <Link href={tabulator(tenantSlug)}>
                        Completar {countOf(accuracy.missing, 'dato', 'datos')}
                    </Link>
                </Button>
            )}
        </div>
    );
}
