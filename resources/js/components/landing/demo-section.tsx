import DemoNextSteps from '@/components/landing/demo-next-steps';
import DemoRequestForm from '@/components/landing/demo-request-form';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

type Props = {
    /** The route of the page the section is on, and its tool: the form starts with it chosen. */
    page?: string | undefined;
    tool?: string | undefined;
};

export default function DemoSection({ page, tool }: Props) {
    return (
        <section
            id="agenda-una-demo"
            aria-labelledby="agenda-una-demo-title"
            className={cn('scroll-mt-16', SECTION)}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-start lg:gap-14',
                )}
            >
                {/* On a phone the column dissolves into the grid, so the steps can follow the form; from lg it is one sticky column. */}
                <div className="contents lg:sticky lg:top-28 lg:flex lg:flex-col lg:gap-4">
                    <h2 id="agenda-una-demo-title" className={TITLE}>
                        Agenda una demo
                    </h2>
                    <p className={LEAD}>
                        Cuéntanos de tu empresa y qué herramientas te interesan,
                        y preparamos la llamada con eso.
                    </p>
                    <DemoNextSteps className="order-last mt-6 lg:order-none lg:mt-3" />
                </div>
                <div className="mt-4 min-w-0 lg:mt-0">
                    <DemoRequestForm page={page} tool={tool} />
                </div>
            </div>
        </section>
    );
}
