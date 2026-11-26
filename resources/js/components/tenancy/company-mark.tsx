import { initials } from '@/lib/initials';

/** The company's logo at the head of its page, or its initials on a soft square while it has none. */
export default function CompanyMark({
    name,
    logoUrl,
}: {
    name: string;
    logoUrl: string | null;
}) {
    return logoUrl === null ? (
        <span
            aria-hidden
            className="inline-flex size-14 shrink-0 items-center justify-center rounded-xl bg-secondary text-xl font-semibold"
        >
            {initials(name)}
        </span>
    ) : (
        <img
            src={logoUrl}
            alt=""
            className="size-14 shrink-0 card object-contain p-1.5"
        />
    );
}
