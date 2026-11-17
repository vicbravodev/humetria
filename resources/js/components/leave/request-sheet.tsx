import RequestForm from '@/components/leave/request-form';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { countOf } from '@/lib/plural';
import type { LeaveTypeOption } from '@/types';

/** «Pedir días» as a sheet from the bottom, over the page (board PedirDias): the form, and nothing else, while they ask. */
export default function RequestSheet({
    slug,
    today,
    types,
    available,
    open,
    onOpenChange,
}: {
    slug: string;
    today: string;
    types: LeaveTypeOption[];
    /** The days the person has today. */
    available: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[92dvh] gap-2 overflow-y-auto rounded-t-2xl bg-popover"
            >
                <SheetHeader className="pr-14">
                    <SheetTitle className="text-xl leading-7">
                        Pedir días
                    </SheetTitle>
                    <SheetDescription className="text-base">
                        {available > 0
                            ? `Le quedan ${countOf(available, 'día disponible', 'días disponibles')}.`
                            : 'Elija sus fechas: quien las apruebe verá su solicitud.'}
                    </SheetDescription>
                </SheetHeader>
                <RequestForm
                    slug={slug}
                    today={today}
                    types={types}
                    available={available}
                />
            </SheetContent>
        </Sheet>
    );
}
