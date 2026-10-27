import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { AppHeaderDesktopNav } from '@/components/app-header/app-header-desktop-nav';
import { AppHeaderExternalLinks } from '@/components/app-header/app-header-external-links';
import { AppHeaderMobileMenu } from '@/components/app-header/app-header-mobile-menu';
import { AppHeaderUserMenu } from '@/components/app-header/app-header-user-menu';
import Logo from '@/components/humetria/logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[] | undefined;
};

export function AppHeader({ breadcrumbs = [] }: Props) {
    return (
        <>
            <div className="border-b border-sidebar-border/80">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    <AppHeaderMobileMenu />

                    <Link
                        href={dashboard()}
                        prefetch
                        className="flex items-center space-x-2"
                    >
                        <Logo className="h-7" />
                    </Link>

                    <AppHeaderDesktopNav />

                    <div className="ml-auto flex items-center space-x-2">
                        <div className="relative flex items-center space-x-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="group size-11 cursor-pointer"
                            >
                                <Search className="!size-5 opacity-80 group-hover:opacity-100" />
                            </Button>
                            <AppHeaderExternalLinks />
                        </div>
                        <AppHeaderUserMenu />
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="flex w-full border-b border-sidebar-border/70">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-muted-foreground md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
