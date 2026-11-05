import { Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import Initials from '@/components/humetria/initials';

/**
 * The message a person reads in their portal when their company channeled them through it (board V4-N035-Canalizacion,
 * «Aviso en su portal»), as «usted». Nothing of what they answered.
 */
export default function PortalSupport({
    company,
    name,
    on,
    message,
}: {
    company: string;
    name: string;
    /** "4 nov 2026". */
    on: string;
    message: string;
}) {
    return (
        <>
            <Head title="Un mensaje de su empresa" />
            <main className="mx-auto flex min-h-svh w-full max-w-[560px] flex-col gap-6 bg-background px-4 py-8">
                <header className="flex items-center justify-between gap-3 border-b pb-4">
                    <span className="flex items-center gap-2.5 font-semibold">
                        <Initials
                            name={company}
                            className="size-8 text-caption"
                        />
                        {company}
                    </span>
                    <span className="flex items-center gap-1.5 text-sm text-primary">
                        <Lock aria-hidden className="size-4" />
                        Confidencial
                    </span>
                </header>
                <section
                    aria-labelledby="support-title"
                    className="flex flex-col gap-4"
                >
                    <p className="text-sm text-muted-foreground">{on}</p>
                    <h1
                        id="support-title"
                        className="text-2xl font-semibold tracking-[-0.01em]"
                    >
                        Hola, {name}
                    </h1>
                    <p className="rounded-xl bg-accent px-4 py-4 text-lg leading-7">
                        {message}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Solo usted ve este mensaje. Su página es personal: no
                        comparta su enlace.
                    </p>
                </section>
                <p className="mt-auto text-center text-caption text-muted-foreground">
                    hecho con humetría
                </p>
            </main>
        </>
    );
}
