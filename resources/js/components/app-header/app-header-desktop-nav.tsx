import { Link } from '@inertiajs/react';
import { mainNavItems } from '@/components/app-header/app-header-nav-items';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

const activeItemStyles = 'bg-accent text-accent-foreground';

export function AppHeaderDesktopNav() {
    const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();

    return (
        <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
            <NavigationMenu className="flex h-full items-stretch">
                <NavigationMenuList className="flex h-full items-stretch space-x-2">
                    {mainNavItems.map((item, index) => (
                        <NavigationMenuItem
                            key={index}
                            className="relative flex h-full items-center"
                        >
                            <Link
                                href={item.href}
                                className={cn(
                                    navigationMenuTriggerStyle(),
                                    whenCurrentUrl(item.href, activeItemStyles),
                                    'h-9 cursor-pointer px-3',
                                )}
                            >
                                {item.icon && (
                                    <item.icon className="mr-2 h-4 w-4" />
                                )}
                                {item.title}
                            </Link>
                            {isCurrentUrl(item.href) && (
                                <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-foreground"></div>
                            )}
                        </NavigationMenuItem>
                    ))}
                </NavigationMenuList>
            </NavigationMenu>
        </div>
    );
}
