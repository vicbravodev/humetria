import Initials from '@/components/humetria/initials';

/**
 * A company with no logo: its initials, and its name beside them. Folded to the initials alone (`symbol`), the name stays
 * for screen readers, so the link around it still says where it goes.
 */
export default function CompanyInitialsMark({
    name,
    kind,
    className,
}: {
    name: string;
    kind: 'logo' | 'symbol';
    className?: string | undefined;
}) {
    return (
        <span className="flex min-w-0 items-center gap-2.5">
            <Initials
                name={name}
                kind="company"
                className={kind === 'symbol' ? className : undefined}
            />
            <span
                className={
                    kind === 'logo' ? 'truncate font-semibold' : 'sr-only'
                }
            >
                {name}
            </span>
        </span>
    );
}
