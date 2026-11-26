import { Head } from '@inertiajs/react';
import AnnouncementsWorkArea from '@/components/announcements/announcements-work-area';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useAnnouncementEditor } from '@/hooks/use-announcement-editor';
import { index } from '@/routes/announcements';
import type {
    AnnouncementOption,
    AnnouncementRow,
    MaintenanceState,
    Paginated,
} from '@/types';

/**
 * «Avisos» (tablero Avisos y mantenimiento): what Humetría tells the companies in their panel, the ones to come or running and
 * the history, each a page at a time, with the form of a new one in the page.
 */
export default function Announcements({
    view,
    counts,
    announcements,
    kinds,
    plans,
    maintenance,
}: {
    view: 'current' | 'history';
    counts: { current: number; history: number };
    announcements: Paginated<AnnouncementRow>;
    kinds: AnnouncementOption[];
    plans: AnnouncementOption[];
    maintenance: MaintenanceState;
}) {
    const editor = useAnnouncementEditor();

    return (
        <>
            <Head title="Avisos" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    caption="Plataforma"
                    title="Avisos y mantenimiento"
                    description="Habla con las empresas desde un solo lugar: un banner en su panel con fecha de inicio y fin, o el modo mantenimiento. Todo queda en la bitácora."
                >
                    <Button asChild>
                        <a
                            href="#nuevo-aviso"
                            onClick={() => editor.load(null)}
                        >
                            Nuevo aviso
                        </a>
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
                <AnnouncementsWorkArea
                    announcements={announcements}
                    history={view === 'history'}
                    catalogs={{ kinds, plans }}
                    maintenance={maintenance}
                    editor={editor}
                />
            </div>
        </>
    );
}
