import { Link } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import { Button } from '@/components/ui/button';
import { useScrolled } from '@/hooks/use-scrolled';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';

const sections = [
    { href: '#como-funciona', label: 'Cómo funciona', test: 'header-how' },
    { href: '#herramientas', label: 'Herramientas', test: 'header-tools' },
    {
        href: '#confidencialidad',
        label: 'Confidencialidad',
        test: 'header-privacy',
    },
    { href: '#humi', label: 'Humi', test: 'header-humi' },
] as const;

export default function LandingHeader() {
    const scrolled = useScrolled();

    // The line under the header only shows once the page moves under it.
    return (
        <header
            className={cn(
                'sticky top-0 z-10 border-b bg-background transition-[border-color] duration-200 ease-out',
                scrolled ? 'border-border' : 'border-transparent',
            )}
        >
            <div className="mx-auto flex h-16 max-w-[1240px] items-center gap-1 px-4 sm:px-6">
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
                {/* A phone's header only fits the logo and the two calls to action. */}
                <nav
                    aria-label="Secciones"
                    className="hidden items-center gap-1 md:flex"
                >
                    {sections.map((section) => (
                        <Button
                            key={section.href}
                            asChild
                            variant="ghost"
                            size="sm"
                            className="font-normal"
                        >
                            <a href={section.href} data-test={section.test}>
                                {section.label}
                            </a>
                        </Button>
                    ))}
                </nav>
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="font-normal"
                >
                    <Link href={dashboard()} data-test="sign-in">
                        Iniciar sesión
                    </Link>
                </Button>
                <Button asChild size="sm" className="ml-2">
                    <a href="#agenda-una-demo" data-test="header-demo">
                        Agenda una demo
                    </a>
                </Button>
            </div>
        </header>
    );
}
