import Logo from '@/components/humetria/logo';
import { WRAP } from '@/components/landing/landing-layout';
import LandingThemeMenu from '@/components/landing/landing-theme-menu';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';

/** The landing's header without its sections: the logo, the theme, the way in and the way back to the home page. */
export default function PrivacyNoticeHeader() {
    return (
        <header className="sticky top-0 z-10 border-b bg-background/80 backdrop-blur-xl backdrop-saturate-150">
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
                <div className="flex items-center gap-1">
                    <span className="hidden sm:block">
                        <LandingThemeMenu />
                    </span>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="font-normal"
                    >
                        <a href={dashboard().url}>Iniciar sesión</a>
                    </Button>
                    <Button
                        asChild
                        size="sm"
                        className="ml-1 rounded-full px-4"
                    >
                        <a href={home().url} data-test="notice-home">
                            Ir al inicio
                        </a>
                    </Button>
                </div>
            </div>
        </header>
    );
}
