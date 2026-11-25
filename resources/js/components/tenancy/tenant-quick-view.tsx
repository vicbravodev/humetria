import { Link } from '@inertiajs/react';
import { PanelRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { seatsText } from '@/lib/tenant-work';
import { show } from '@/routes/companies';
import type { TenantRow } from '@/types';

/**
 * A company at a glance from its row (tablero Empresas, «Vista rápida»): where it stands, who owns it, who answers for it and its
 * seats, with the way to its whole page. It reads only what the row already has.
 */
export default function TenantQuickView({ tenant }: { tenant: TenantRow }) {
    const facts: [string, string][] = [
        ['Estado', tenant.status_date ?? tenant.status_label],
        ['RFC', tenant.rfc],
        ['Dueño', tenant.owner?.name ?? 'Sin dueño'],
        ['Correo del dueño', tenant.owner?.email ?? '—'],
        ['Asientos', seatsText(tenant.seats)],
        ['Responsable', tenant.account_manager?.name ?? 'Sin asignar'],
        ['Último acceso', tenant.last_access ?? 'Nunca'],
    ];

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Vista rápida de ${tenant.trade_name}`}
                >
                    <PanelRight aria-hidden />
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full gap-0 bg-popover sm:inset-y-2 sm:right-2 sm:h-auto sm:max-w-[400px] sm:rounded-xl sm:border-0 sm:shadow-float">
                <SheetHeader className="flex-row items-center gap-3 border-b px-6 pt-5 pb-4">
                    <Initials name={tenant.trade_name} kind="company" />
                    <div className="grid gap-0.5">
                        <SheetTitle>{tenant.trade_name}</SheetTitle>
                        <SheetDescription>{tenant.legal_name}</SheetDescription>
                    </div>
                </SheetHeader>
                <div className="grid content-start gap-4 overflow-y-auto px-6 py-5">
                    <Badge
                        variant={tenantStatusVariant(tenant.status)}
                        className="justify-self-start"
                    >
                        {tenant.status_label}
                    </Badge>
                    <dl className="grid gap-3 text-sm">
                        {facts.map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-4"
                            >
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="text-right font-medium [overflow-wrap:anywhere]">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <Button asChild>
                        <Link href={show(tenant.id)}>Abrir la empresa</Link>
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
