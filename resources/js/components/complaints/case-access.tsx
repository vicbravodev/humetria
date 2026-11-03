import { Users } from 'lucide-react';
import { countOf } from '@/lib/plural';

/** Who has the case: the people it was assigned to, by name. Nobody else can open it. */
export default function CaseAccess({ names }: { names: string[] }) {
    return (
        <section className="flex flex-col gap-2 text-meta">
            <h2 className="flex items-center gap-2 font-semibold">
                <Users aria-hidden className="size-4 text-muted-foreground" />
                {countOf(names.length, 'persona tiene', 'personas tienen')} este
                caso
            </h2>
            <p className="text-muted-foreground">{names.join(', ')}</p>
        </section>
    );
}
