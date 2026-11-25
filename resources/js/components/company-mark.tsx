import { usePage } from '@inertiajs/react';
import CompanyInitialsMark from '@/components/company-initials-mark';
import CompanyLogoImage from '@/components/company-logo-image';
import Initials from '@/components/humetria/initials';
import Logo from '@/components/humetria/logo';

/**
 * Whose pages these are (white label): inside a company, its logo, or its initials and name while it has none; outside
 * one, `name` as plain text, or Humetría's own logo when no name is asked. Humetría stays only as the "hecho con
 * humetría" seal (`Logo kind="seal"`), never here.
 */
export default function CompanyMark({
    name,
    kind = 'logo',
    badge = false,
    className,
    humetriaClassName = className,
}: {
    /** The company's name where the page already carries it; without it, outside a company, the mark is Humetría's. */
    name?: string;
    kind?: 'logo' | 'symbol';
    /** Outside a company, still put the initials before `name`. */
    badge?: boolean;
    /** The height of the logo (`h-8`), the size of the initials (`size-8`). */
    className?: string | undefined;
    /** The size of Humetría's own logo, where no company's takes its place (its logo is wide and short: it wants less height). */
    humetriaClassName?: string | undefined;
}) {
    const { brand } = usePage().props;

    if (brand === null) {
        return name === undefined ? (
            <Logo kind={kind} className={humetriaClassName} />
        ) : badge ? (
            <span className="flex min-w-0 items-center gap-3">
                <Initials name={name} kind="company" />
                {name}
            </span>
        ) : (
            <>{name}</>
        );
    }

    if (brand.logo !== null && kind === 'logo') {
        return (
            <CompanyLogoImage
                name={brand.name}
                logo={brand.logo}
                darkLogo={brand.darkLogo}
                className={className}
            />
        );
    }

    return (
        <CompanyInitialsMark
            name={brand.name}
            kind={kind}
            className={className}
        />
    );
}
