import CountTabs from '@/components/humetria/count-tabs';
import { index } from '@/routes/demo-requests';
import type { DemoLayout } from '@/types';

/** The funnel or the list of every request; each is its own address. */
export default function DemoViewTabs({ view }: { view: DemoLayout }) {
    return (
        <CountTabs
            label="Vista de las solicitudes"
            tabs={[
                {
                    label: 'Embudo',
                    href: index.url({ query: { view: 'board' } }),
                    active: view === 'board',
                },
                { label: 'Lista', href: index.url(), active: view === 'list' },
            ]}
        />
    );
}
