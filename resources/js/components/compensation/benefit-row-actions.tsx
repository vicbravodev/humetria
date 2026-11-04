import { router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Ellipsis,
    Pencil,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import EditBenefitSheet from '@/components/compensation/edit-benefit-sheet';
import TermsSheet from '@/components/compensation/terms-sheet';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { archive, restore } from '@/routes/tenant/compensation/benefits';
import type { BenefitChoices, BenefitRow } from '@/types';

type Action = 'terms' | 'edit' | 'archive' | 'restore';

/**
 * Every benefit has its terms edited. One the company made up can also be edited and archived (never deleted: its
 * terms are kept); a base one cannot, and an archived one is restored.
 */
export default function BenefitRowActions({
    benefit,
    choices,
}: {
    benefit: BenefitRow;
    choices: BenefitChoices;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState<Action | null>(null);
    const sheet = (action: Action) => ({
        open: open === action,
        onOpenChange: (isOpen: boolean) => setOpen(isOpen ? action : null),
    });
    const ids = { tenant: tenantSlug, benefit: benefit.id };

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${benefit.name}`}
                        data-test="benefit-actions"
                        className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="min-w-48"
                    // Sent once the menu has closed: a page redrawn mid-animation leaves the menu unable to open again.
                    onCloseAutoFocus={() => {
                        if (open === 'archive' || open === 'restore') {
                            const send = open === 'archive' ? archive : restore;

                            setOpen(null);
                            router.post(
                                send.url(ids),
                                {},
                                { preserveScroll: true },
                            );
                        }
                    }}
                >
                    {benefit.archived ? (
                        <DropdownMenuItem onSelect={() => setOpen('restore')}>
                            <ArchiveRestore />
                            Restaurar
                        </DropdownMenuItem>
                    ) : (
                        <>
                            <DropdownMenuItem onSelect={() => setOpen('terms')}>
                                <SlidersHorizontal />
                                Términos
                            </DropdownMenuItem>
                            {!benefit.is_base && (
                                <>
                                    <DropdownMenuItem
                                        onSelect={() => setOpen('edit')}
                                    >
                                        <Pencil />
                                        Editar
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onSelect={() => setOpen('archive')}
                                    >
                                        <Archive />
                                        Archivar
                                    </DropdownMenuItem>
                                </>
                            )}
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <TermsSheet benefit={benefit} {...sheet('terms')} />
            {!benefit.is_base && (
                <EditBenefitSheet
                    benefit={benefit}
                    choices={choices}
                    {...sheet('edit')}
                />
            )}
        </>
    );
}
