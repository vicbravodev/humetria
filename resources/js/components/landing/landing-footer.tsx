import { Link } from '@inertiajs/react';
import Logo from '@/components/humetria/logo';
import { WRAP } from '@/components/landing/landing-layout';
import { ThemeSwitch } from '@/components/theme-switch';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const year = new Date().getFullYear();

const links = [
    { href: '#como-funciona', label: 'Cómo funciona' },
    { href: '#herramientas', label: 'Herramientas' },
    { href: '#confidencialidad', label: 'Confidencialidad' },
    { href: '#humi', label: 'Humi' },
];

const linkClass =
    'inline-flex min-h-11 items-center rounded-md text-meta text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/** The logo, the sections again, the theme switch and what Humetría does and does not do. */
export default function LandingFooter() {
    return (
        <footer className="border-t pt-10 pb-8">
            <div
                className={cn(
                    WRAP,
                    'grid gap-7 md:grid-cols-[1fr_auto] md:items-center',
                )}
            >
                <div className="grid content-start gap-3">
                    <Logo className="h-7" />
                    <nav
                        aria-label="Pie"
                        className="flex flex-wrap gap-x-[22px]"
                    >
                        {links.map((link) => (
                            <a
                                key={link.href}
                                href={link.href}
                                className={linkClass}
                            >
                                {link.label}
                            </a>
                        ))}
                        <Link href={dashboard()} className={linkClass}>
                            Iniciar sesión
                        </Link>
                    </nav>
                </div>
                <div className="justify-self-start md:justify-self-end">
                    <ThemeSwitch />
                </div>
                <div className="grid gap-1.5 text-sm text-muted-foreground md:col-span-2 md:flex md:flex-wrap md:justify-between md:gap-4 md:border-t md:pt-5">
                    <p>Hecho en México · {year} Humetría</p>
                    <p>Humetría ordena evidencia; no certifica cumplimiento.</p>
                </div>
            </div>
        </footer>
    );
}
