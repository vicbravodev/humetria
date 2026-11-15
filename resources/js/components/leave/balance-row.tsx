import { Link, router } from '@inertiajs/react';
import { CalendarClock, FilePen, Mail } from 'lucide-react';
import ChooseBox from '@/components/humetria/choose-box';
import Initials from '@/components/humetria/initials';
import RowMenu from '@/components/humetria/row-menu';
import type { RowMenuItem } from '@/components/humetria/row-menu';
import BalanceDays from '@/components/leave/balance-days';
import BalanceNext from '@/components/leave/balance-next';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { links } from '@/routes/tenant/vacations/balances';
import { show } from '@/routes/tenant/vacations/employees';
import type { LeaveBalanceRow } from '@/types';

/**
 * A person of the list of balances: their name (the way to their record) with the policy under it, the days they can ask
 * for as a number and a bar, and when the next period starts. What else RH does with them is in the menu of the row.
 */
export default function BalanceRow({
    person,
    today,
    selectable,
    chosen,
    onChoose,
}: {
    person: LeaveBalanceRow;
    today: string;
    selectable: boolean;
    chosen: boolean;
    onChoose: (checked: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    const record = show.url({ tenant: tenantSlug, employee: person.id });
    const items: RowMenuItem[] = [
        { label: 'Ver expediente', icon: CalendarClock, href: record },
        ...(selectable
            ? [
                  { label: 'Ajustar días', icon: FilePen, href: record },
                  {
                      label: 'Enviar constancia',
                      icon: Mail,
                      onSelect: () =>
                          router.post(
                              links.url(tenantSlug),
                              { employees: [person.id] },
                              { preserveScroll: true },
                          ),
                  },
              ]
            : []),
    ];

    return (
        <TableRow data-state={chosen ? 'selected' : undefined}>
            {selectable && (
                <TableCell>
                    <ChooseBox
                        checked={chosen}
                        onChange={onChoose}
                        name={`Elegir a ${person.name}`}
                    />
                </TableCell>
            )}
            <TableCell className="h-14 whitespace-normal max-md:w-full">
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="min-w-36">
                        <Link
                            href={record}
                            className="touch-hit font-medium hover:underline"
                        >
                            {person.name}
                        </Link>
                        <p className="text-caption text-muted-foreground">
                            {person.policy}
                        </p>
                    </div>
                    {!person.active && <Badge variant="secondary">Baja</Badge>}
                </div>
            </TableCell>
            <TableCell className="whitespace-normal md:w-56">
                <BalanceDays person={person} />
            </TableCell>
            <TableCell className="whitespace-normal">
                <BalanceNext person={person} today={today} />
            </TableCell>
            <TableCell className="text-right">
                <RowMenu label={`Acciones de ${person.name}`} items={items} />
            </TableCell>
        </TableRow>
    );
}
