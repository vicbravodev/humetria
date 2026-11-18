import Logo from '@/components/humetria/logo';
import { WRAP } from '@/components/landing/landing-layout';
import LandingThemeMenu from '@/components/landing/landing-theme-menu';
import ToolsMenu from '@/components/landing/tools-menu';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';

const SECTIONS = [
    { href: '#como-funciona', label: 'Cómo funciona' },
    { href: '#confidencialidad', label: 'Confidencialidad' },
];

/** The header of a tool's page and of the guide: the tools, the way back to the landing's sections, the way in and the demo. */
export default function ToolPageHeader() {
    return (
        <header className="sticky top-0 z-10 border-b bg-background">
            <div
                className={cn(
                    WRAP,
                    'flex h-16 items-center justify-between gap-3',
                )}
            >
                <a
                    href={home().url}
                    aria-label="Humetría, inicio"
                    className="flex min-h-11 items-center rounded-md"
                >
                    <span className="sm:hidden">
                        <Logo kind="symbol" className="size-8" />
                    </span>
                    <span className="hidden sm:block">
                        <Logo className="h-7" />
                    </span>
                </a>
                <nav
                    aria-label="Principal"
                    className="hidden items-center gap-1 md:flex"
                >
                    <ToolsMenu variant="bar" />
                    {SECTIONS.map((section) => (
                        <a
                            key={section.href}
                            href={`${home().url}${section.href}`}
                            className="inline-flex min-h-11 items-center rounded-full px-3.5 text-sm text-muted-foreground hover:text-foreground"
                        >
                            {section.label}
                        </a>
                    ))}
                </nav>
                <div className="flex items-center gap-1.5">
                    <span className="hidden sm:block">
                        <LandingThemeMenu />
                    </span>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="hidden min-h-11 font-normal md:inline-flex"
                    >
                        <a href={dashboard().url}>Iniciar sesión</a>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        className="min-h-11 rounded-full px-4"
                    >
                        <a
                            href="#agenda-una-demo"
                            data-track="cta_click"
                            data-track-target="header_demo"
                            data-test="header-demo"
                        >
                            Agenda una demo
                        </a>
                    </Button>
                    <ToolsMenu variant="icon" className="md:hidden" />
                </div>
            </div>
        </header>
    );
}
