import { PanelRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import TenantPeekBody from '@/components/tenancy/tenant-peek-body';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { TenantRow } from '@/types';

/**
 * A company at a glance from its row (tablero Empresas, «Vista rápida»): where it stands, who owns it, who answers for it and its
 * seats, with the way to its whole page. It reads only what the row already has.
 */
/** The company whose quick view is docked beside the table, and how to choose another. */
export type TenantPeek = { id: string; choose: (id: string) => void };

export default function TenantQuickView({
    tenant,
    peek,
}: {
    tenant: TenantRow;
    /** On a wide screen the quick view is docked: the button chooses the company instead of opening a sheet. */
    peek: TenantPeek | null;
}) {
    if (peek !== null) {
        return (
            <Button
                variant="ghost"
                size="icon"
                aria-label={`Vista rápida de ${tenant.trade_name}`}
                aria-pressed={peek.id === tenant.id}
                onClick={() => peek.choose(tenant.id)}
            >
                <PanelRight aria-hidden />
            </Button>
        );
    }

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
                <div className="overflow-y-auto px-6 py-5">
                    <TenantPeekBody tenant={tenant} />
                </div>
            </SheetContent>
        </Sheet>
    );
}
