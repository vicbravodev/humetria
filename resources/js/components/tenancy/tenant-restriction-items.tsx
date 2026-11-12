import { Archive, Ban, Eye } from 'lucide-react';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { restrictionCopy, type Restriction } from '@/lib/tenant-restriction';
import type { TenantRow } from '@/types';

const ITEMS = [
    { restriction: 'read_only', icon: Eye },
    { restriction: 'suspended', icon: Ban },
    { restriction: 'archived', icon: Archive },
] satisfies { restriction: Restriction; icon: typeof Eye }[];

/**
 * The last items of the "…" menu, apart: put the company in read only, suspend or archive it. Every one it is not
 * already in; reading is the mild one, the other two cut its people off and show in the destructive color.
 */
export default function TenantRestrictionItems({
    tenant,
    onChoose,
}: {
    tenant: TenantRow;
    onChoose: (restriction: Restriction) => void;
}) {
    return (
        <>
            <DropdownMenuSeparator />
            {ITEMS.filter((item) => item.restriction !== tenant.status).map(
                ({ restriction, icon: Icon }) => (
                    <DropdownMenuItem
                        key={restriction}
                        variant={
                            restriction === 'read_only'
                                ? 'default'
                                : 'destructive'
                        }
                        onSelect={() => onChoose(restriction)}
                    >
                        <Icon />
                        {restrictionCopy(restriction, tenant.trade_name).action}
                    </DropdownMenuItem>
                ),
            )}
        </>
    );
}
