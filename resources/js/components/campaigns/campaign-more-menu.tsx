import { Link } from '@inertiajs/react';
import { Ellipsis } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { results } from '@/routes/tenant/campaigns';
import type { CampaignDialog, CampaignKind } from '@/types';

/** The "⋯" of a running evaluation: what is done now and then, so the header keeps only the usual. */
export default function CampaignMoreMenu({
    route,
    kind,
    canReplace,
    onOpen,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    kind: CampaignKind;
    canReplace: boolean;
    onOpen: (dialog: CampaignDialog) => void;
}) {
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label="Más acciones">
                    <Ellipsis aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <Link href={results(route)}>Ver resultados</Link>
                </DropdownMenuItem>
                {kind === 'nom035_evaluation' && (
                    <DropdownMenuItem onSelect={() => onOpen('invite')}>
                        Invitar a quien falte
                    </DropdownMenuItem>
                )}
                {canReplace && (
                    <DropdownMenuItem onSelect={() => onOpen('replacements')}>
                        Sortear reemplazos
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    onSelect={() => onOpen('close')}
                >
                    Cerrar evaluación
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
