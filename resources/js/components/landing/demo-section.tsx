import DemoNextSteps from '@/components/landing/demo-next-steps';
import DemoRequestForm from '@/components/landing/demo-request-form';
import {
    LEAD,
    SECTION,
    TITLE,
    WRAP,
} from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

export default function DemoSection() {
    return (
        <section
            id="agenda-una-demo"
            aria-labelledby="agenda-una-demo-title"
            className={cn('scroll-mt-16', SECTION)}
        >
            <div
                className={cn(
                    WRAP,
                    'grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-start lg:gap-14',
                )}
            >
                <div className="flex flex-col gap-4 lg:sticky lg:top-28">
                    <h2 id="agenda-una-demo-title" className={TITLE}>
                        Agenda una demo
                    </h2>
                    <p className={LEAD}>
                        Cuéntanos qué herramientas te interesan y te mostramos
                        Humetría con tu caso. La prueba gratis se acuerda en la
                        misma llamada, para que empieces con tu gente sin perder
                        tiempo.
                    </p>
                    <DemoNextSteps />
                </div>
                <div className="min-w-0">
                    <DemoRequestForm />
                </div>
            </div>
        </section>
    );
}
