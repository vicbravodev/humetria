import { Scale } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { Badge } from '@/components/ui/badge';
import { countOf } from '@/lib/plural';
import type { LeaveLaw } from '@/types';

/** What the law gives (LFT art. 76 and 80), always the first card and without actions: no policy can go under it. */
export default function LawCard({ law }: { law: LeaveLaw }) {
    return (
        <article className="flex min-w-0 flex-col gap-3 card bg-muted p-[22px]">
            <div className="flex items-center gap-3">
                <IconTile icon={Scale} />
                <h2 className="text-lg leading-[26px] font-semibold">Ley</h2>
                <Badge variant="secondary">Mínimo</Badge>
            </div>
            <p className="text-sm text-muted-foreground">
                {countOf(law.firstYearDays, 'día', 'días')} el primer año ·
                prima {law.premiumPercent} % · no se puede editar
            </p>
            <p className="text-caption text-muted-foreground">
                Es lo que sigue quien no tiene una política asignada. Las
                políticas de la empresa solo pueden mejorarla.
            </p>
        </article>
    );
}
