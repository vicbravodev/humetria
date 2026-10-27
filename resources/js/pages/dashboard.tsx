import { Head } from '@inertiajs/react';
import PageHeader from '@/components/page-header';

export default function Dashboard() {
    return (
        <>
            <Head title="Panel" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Panel" />
                <section className="rounded-lg border bg-card p-6">
                    <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                        Aún no hay campañas
                    </h2>
                    <p className="mt-1 text-muted-foreground">
                        Cuando lances la primera campaña, aquí verás su avance.
                    </p>
                </section>
            </div>
        </>
    );
}
