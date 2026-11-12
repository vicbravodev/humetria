import { useState } from 'react';
import DecisionPanel from '@/components/leave/decision-panel';
import RequestsTable from '@/components/leave/requests-table';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useMediaQuery } from '@/hooks/use-media-query';
import type { LeaveQueueRequest } from '@/types';

/**
 * The list and the decision panel of the selected request, side by side (the first one is selected on arriving). On a
 * phone the panel is a sheet that opens from the row, with the answers at its foot.
 */
export default function RequestsWorkspace({
    requests,
}: {
    requests: LeaveQueueRequest[];
}) {
    const wide = useMediaQuery('(min-width: 1024px)');
    const [chosenId, setChosenId] = useState<string | undefined>(undefined);
    const [sheetOpen, setSheetOpen] = useState(false);
    // A request that left this tab (it was answered) gives its place to the first one.
    const selected =
        requests.find((request) => request.id === chosenId) ?? requests[0];

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,58fr)_minmax(0,42fr)]">
            <RequestsTable
                requests={requests}
                selectedId={selected?.id}
                onSelect={(id) => {
                    setChosenId(id);
                    setSheetOpen(true);
                }}
            />
            {wide && selected !== undefined && (
                <div className="sticky top-6">
                    <DecisionPanel request={selected} />
                </div>
            )}
            {!wide && (
                <Sheet
                    open={sheetOpen && selected !== undefined}
                    onOpenChange={setSheetOpen}
                >
                    <SheetContent
                        side="bottom"
                        className="max-h-[92dvh] gap-0 overflow-y-auto rounded-t-xl bg-popover"
                    >
                        <SheetHeader className="sr-only">
                            <SheetTitle>
                                Solicitud de {selected?.name}
                            </SheetTitle>
                            <SheetDescription>
                                Lee la solicitud y respóndela.
                            </SheetDescription>
                        </SheetHeader>
                        {selected !== undefined && (
                            <DecisionPanel request={selected} />
                        )}
                    </SheetContent>
                </Sheet>
            )}
        </div>
    );
}
