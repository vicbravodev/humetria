import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import PageHeader from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        href: edit(),
        icon: null,
    },
    {
        title: 'Seguridad',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Apariencia',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
            <PageHeader caption="Cuenta" title="Configuración" />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col gap-1"
                        aria-label="Configuración"
                    >
                        {sidebarNavItems.map((item) => {
                            const current = isCurrentOrParentUrl(item.href);

                            return (
                                <Link
                                    key={toUrl(item.href)}
                                    href={item.href}
                                    aria-current={current ? 'page' : undefined}
                                    className={cn(
                                        'flex h-11 items-center rounded-lg px-3 text-base transition-colors duration-150 ease-out hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                        current
                                            ? 'bg-accent font-semibold text-accent-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
