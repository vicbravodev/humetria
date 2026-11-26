import { cn } from '@/lib/utils';
import type { Brand } from '@/types';

type Logo = NonNullable<Brand['logo']>;

/** The logo of a company: the one for light, and on a dark page its own dark version when it has one. */
export default function CompanyLogoImage({
    name,
    logo,
    darkLogo,
    className,
}: {
    name: string;
    logo: Logo;
    darkLogo: Logo | null;
    className?: string | undefined;
}) {
    return (
        <>
            <img
                src={logo.url}
                alt={name}
                width={logo.width}
                height={logo.height}
                className={cn(
                    'w-auto max-w-full object-contain',
                    darkLogo !== null && 'dark:hidden',
                    className,
                )}
            />
            {darkLogo !== null && (
                <img
                    src={darkLogo.url}
                    alt={name}
                    width={darkLogo.width}
                    height={darkLogo.height}
                    className={cn(
                        'hidden w-auto max-w-full object-contain dark:block',
                        className,
                    )}
                />
            )}
        </>
    );
}
