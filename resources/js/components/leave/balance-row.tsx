import { Link } from '@inertiajs/react';
import ChooseBox from '@/components/humetria/choose-box';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { balanceNote } from '@/lib/leave/balances';
import { dateSpan } from '@/lib/leave/format';
import { countOf } from '@/lib/plural';
import { index as employees } from '@/routes/tenant/employees';
import { show } from '@/routes/tenant/vacations/employees';
import type { LeaveBalanceRow } from '@/types';

/** A person of the list of balances: their policy, the days they can ask for and when the next period starts. */
export default function BalanceRow({
    person,
    selectable,
    chosen,
    onChoose,
}: {
    person: LeaveBalanceRow;
    selectable: boolean;
    chosen: boolean;
    onChoose: (checked: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <TableRow>
            {selectable && (
                <TableCell className="max-md:hidden">
                    <ChooseBox
                        checked={chosen}
                        onChange={onChoose}
                        name={`Elegir a ${person.name}`}
                    />
                </TableCell>
            )}
            <TableCell className="whitespace-normal max-md:w-full">
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="min-w-36">
                        <p className="font-medium">{person.name}</p>
                        <p className="text-caption text-muted-foreground">
                            Política: {person.policy}
                        </p>
                    </div>
                    {!person.active && <Badge variant="secondary">Baja</Badge>}
                </div>
            </TableCell>
            <TableCell className="whitespace-normal">
                <span className="font-medium tabular-nums">
                    {countOf(person.available, 'día', 'días')}
                </span>
                <span className="block text-caption text-muted-foreground">
                    {balanceNote(person)}
                </span>
            </TableCell>
            <TableCell className="whitespace-normal text-muted-foreground">
                {person.nextAnniversary === null ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={employees.url(tenantSlug, {
                                query: { q: person.name },
                            })}
                        >
                            Capturar la fecha
                        </Link>
                    </Button>
                ) : (
                    dateSpan(person.nextAnniversary, person.nextAnniversary)
                )}
            </TableCell>
            <TableCell className="text-right">
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={show.url({
                            tenant: tenantSlug,
                            employee: person.id,
                        })}
                        aria-label={`Ver el expediente de ${person.name}`}
                    >
                        Expediente
                    </Link>
                </Button>
            </TableCell>
        </TableRow>
    );
}
