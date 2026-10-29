import { CalendarCheck } from 'lucide-react';
import DemoRequestForm from '@/components/landing/demo-request-form';

export default function DemoSection() {
    return (
        <section
            id="agenda-una-demo"
            aria-labelledby="agenda-una-demo-title"
            className="mx-auto max-w-6xl scroll-mt-16 px-4 pb-16 sm:px-6 lg:px-8 lg:pb-24"
        >
            <div className="rounded-xl bg-accent px-4 py-12 text-center sm:px-12 lg:py-16">
                <CalendarCheck
                    aria-hidden
                    className="mx-auto size-8 text-primary"
                />
                <h2
                    id="agenda-una-demo-title"
                    className="mt-5 text-2xl leading-8 font-semibold tracking-[-0.01em] sm:text-3xl sm:leading-[38px]"
                >
                    Agenda una demo
                </h2>
                <p className="mx-auto mt-4 max-w-2xl text-lg leading-7 text-pretty text-accent-foreground">
                    Cuéntanos qué herramientas te interesan y te mostramos
                    Humetría con un caso parecido al tuyo. La prueba gratis
                    también se agenda: la platicamos en la misma llamada para
                    que empieces con tu gente sin perder tiempo.
                </p>
                <div className="mx-auto mt-10 max-w-3xl">
                    <DemoRequestForm />
                </div>
            </div>
        </section>
    );
}
