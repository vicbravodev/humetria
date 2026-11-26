import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AnnouncementDialog from '@/components/announcements/announcement-dialog';
import AnnouncementsTable from '@/components/announcements/announcements-table';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/announcements';
import type { AnnouncementOption, AnnouncementRow, Paginated } from '@/types';

/**
 * «Avisos» (tablero Avisos y mantenimiento): what Humetría tells the companies in their panel, the ones to come or running and
 * the history, each a page at a time; a new one or a change opens its dialog.
 */
export default function Announcements({
    view,
    counts,
    announcements,
    kinds,
    plans,
}: {
    view: 'current' | 'history';
    counts: { current: number; history: number };
    announcements: Paginated<AnnouncementRow>;
    kinds: AnnouncementOption[];
    plans: AnnouncementOption[];
}) {
    // The announcement being edited, null for a new one; undefined while no dialog is open.
    const [editing, setEditing] = useState<
        AnnouncementRow | null | undefined
    >();

    return (
        <>
            <Head title="Avisos" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Plataforma"
                    title="Avisos y mantenimiento"
                    description="Habla con las empresas desde un solo lugar: un banner en su panel, con fecha de inicio y fin. Todo queda en la bitácora."
                >
                    <Button onClick={() => setEditing(null)}>
                        Nuevo aviso
                    </Button>
                </PageHeader>
                <CountTabs
                    label="Avisos por estado"
                    tabs={[
                        {
                            label: 'Programados y activos',
                            count: counts.current,
                            href: index(),
                            active: view === 'current',
                        },
                        {
                            label: 'Historial',
                            count: counts.history,
                            href: index({ query: { view: 'history' } }),
                            active: view === 'history',
                        },
                    ]}
                />
                <AnnouncementsTable
                    announcements={announcements}
                    history={view === 'history'}
                    onEdit={setEditing}
                />
            </div>
            {editing !== undefined && (
                <AnnouncementDialog
                    // A new key per announcement, so its fields start from what it says.
                    key={editing?.id ?? 'new'}
                    announcement={editing}
                    kinds={kinds}
                    plans={plans}
                    open
                    onOpenChange={(open) => !open && setEditing(undefined)}
                />
            )}
        </>
    );
}
