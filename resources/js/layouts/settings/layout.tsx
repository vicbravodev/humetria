import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ShieldCheck, Sun, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import PageHeader from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { settingsNavItems } from '@/lib/app-navigation';
import { cn, toUrl } from '@/lib/utils';

const ICONS: Record<string, LucideIcon> = {
    Perfil: UserRound,
    Seguridad: ShieldCheck,
    Apariencia: Sun,
};

/** The personal account (pantallas: Ajustes): its sections at the left, the one open in a card at the right. */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { impersonator } = usePage().props;

    return (
        <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
            <PageHeader
                title="Configuración"
                description="Tu cuenta personal. Lo de la empresa está en Usuarios."
            />

            <div className="flex flex-wrap items-start gap-8">
                <nav
                    className="flex w-full flex-col gap-0.5 md:max-w-[220px] md:flex-[1_1_200px]"
                    aria-label="Configuración"
                >
                    {settingsNavItems(impersonator !== null).map((item) => {
                        const current = isCurrentOrParentUrl(item.href);
                        const Icon = ICONS[item.title];

                        return (
                            <Link
                                key={toUrl(item.href)}
                                href={item.href}
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-11 items-center gap-3 rounded-lg px-2.5 text-[15px] transition-colors duration-150 ease-out hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&>svg]:size-5 [&>svg]:text-muted-foreground',
                                    current &&
                                        'bg-card font-medium ring-1 ring-border hover:bg-card [&>svg]:text-primary',
                                )}
                            >
                                {Icon !== undefined && <Icon aria-hidden />}
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>

                <div className="flex min-w-0 flex-[999_1_480px] flex-col gap-10 rounded-xl border bg-card p-5 md:p-7">
                    {children}
                </div>
            </div>
        </div>
    );
}
