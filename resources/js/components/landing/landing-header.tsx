import { Link } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

export default function LandingHeader() {
    return (
        <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
            <div className="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4 sm:px-6 lg:px-8">
                <Link
                    href={home()}
                    className="mr-auto flex min-h-11 items-center rounded-md"
                >
                    <span className="sm:hidden">
                        <Logo kind="symbol" className="size-8" />
                    </span>
                    <span className="hidden sm:block">
                        <Logo className="h-7" />
                    </span>
                </Link>
                <Button asChild variant="ghost">
                    <Link href={dashboard()} data-test="sign-in">
                        Iniciar sesión
                    </Link>
                </Button>
                <Button asChild>
                    <a href="#agenda-una-demo" data-test="header-demo">
                        Agenda una demo
                    </a>
                </Button>
            </div>
        </header>
    );
}
