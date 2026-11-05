import { Link } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import type { AccidentNotice } from '@/types';

/** Who sent the accident notice (board V3-Aviso), with the way to their page for RH. */
export default function AccidentPerson({ notice }: { notice: AccidentNotice }) {
    return (
        <section
            aria-label="Quién avisó"
            className="flex flex-col gap-3 card p-5"
        >
            <div className="flex items-center gap-3">
                <Initials
                    name={notice.person}
                    className="size-11 bg-accent text-sm text-primary"
                />
                <div className="min-w-0">
                    <p className="font-semibold">{notice.person}</p>
                    <p className="text-sm text-muted-foreground">
                        {notice.jobTitle} · {notice.percentage} % en teletrabajo
                    </p>
                </div>
            </div>
            {notice.personHref !== null && (
                <Link
                    href={notice.personHref}
                    className="touch-hit text-sm font-medium text-primary hover:underline"
                >
                    Ver su ficha
                </Link>
            )}
        </section>
    );
}
