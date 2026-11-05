import { Head } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import LinkUnavailable from '@/components/link-unavailable';

/**
 * The page of a link that does not open, for any reason (pantalla Enlace-Vencido): it names no company and no cause, so a
 * stranger learns nothing but that it does not work.
 */
export default function PublicInactive() {
    return (
        <div className="flex min-h-dvh flex-col bg-background text-lg leading-7">
            <Head title="Enlace no disponible" />
            <main className="flex w-full flex-1 items-center justify-center px-4 py-10">
                <LinkUnavailable>
                    Pídale a Recursos Humanos de su empresa el enlace vigente.
                </LinkUnavailable>
            </main>
            <footer className="flex justify-center px-4 py-3.5">
                <Logo kind="seal" className="h-4" />
            </footer>
        </div>
    );
}
