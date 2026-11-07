import { Link } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import HeaderNav from '@/components/landing/header-nav';
import LandingThemeMenu from '@/components/landing/landing-theme-menu';
import { Button } from '@/components/ui/button';
import { useScrolled } from '@/hooks/use-scrolled';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';

/**
 * Logo, the sections in a pill at the center and the two ways in at the right. Over the hero it has no edge; once the
 * page moves under it, it takes the background, a line and a soft shadow.
 */
export default function LandingHeader() {
    const scrolled = useScrolled();

    return (
        <header
            className={cn(
                'sticky top-0 z-10 border-b transition-[background-color,border-color,box-shadow] duration-200 ease-out',
                // Once content passes under it, it turns translucent and blurs it, so the page shows through.
                scrolled
                    ? 'border-border bg-background/80 shadow-raised backdrop-blur-xl backdrop-saturate-150'
                    : 'border-transparent bg-background',
            )}
        >
            <div className="mx-auto grid h-16 max-w-[1240px] grid-cols-[1fr_auto] items-center gap-3 px-4 sm:px-6 md:grid-cols-[1fr_auto_1fr]">
                <Link
                    href={home()}
                    className="flex min-h-11 items-center justify-self-start rounded-md"
                >
                    <span className="sm:hidden">
                        <Logo kind="symbol" className="size-8" />
                    </span>
                    <span className="hidden sm:block">
                        <Logo className="h-7" />
                    </span>
                </Link>
                <HeaderNav />
                <div className="flex items-center gap-1 justify-self-end">
                    {/* On a phone the theme lives in the footer: the header only fits the logo and the two ways in. */}
                    <span className="hidden sm:block">
                        <LandingThemeMenu />
                    </span>
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
                    <Button
                        asChild
                        size="sm"
                        className="ml-1 rounded-full px-4"
                    >
                        <a href="#agenda-una-demo" data-test="header-demo">
                            Agenda una demo
                        </a>
                    </Button>
                </div>
            </div>
        </header>
    );
}
