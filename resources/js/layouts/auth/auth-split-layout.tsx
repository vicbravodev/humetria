import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Logo from '@/components/humetria/logo';
import AuthPromises from '@/layouts/auth/auth-promises';
import { home } from '@/routes';

/**
 * The frame of every access screen (pantallas: Login, Olvide, Restablecer, Confirmar, Invitacion): the form on the
 * left in a 400 px column, and on wide screens the jade panel with what Humetría promises.
 */
export default function AuthSplitLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-dvh bg-background">
            <main className="flex min-w-0 flex-1 flex-col px-4 py-8 sm:px-10">
                <Link href={home()} className="touch-hit self-start rounded-md">
                    <Logo className="h-[26px]" />
                </Link>
                <div className="flex flex-1 items-center justify-center py-10">
                    <div className="flex w-full max-w-[400px] flex-col gap-7">
                        {children}
                    </div>
                </div>
            </main>
            <AuthPromises />
        </div>
    );
}
