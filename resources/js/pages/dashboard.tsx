import { Head } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';

export default function Dashboard() {
    return (
        <>
            <Head title="Panel" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Panel" />
                <EmptyState icon={ClipboardList} title="Aún no hay campañas">
                    Cuando lances la primera campaña, aquí verás su avance.
                </EmptyState>
            </div>
        </>
    );
}
