import { FileText } from 'lucide-react';
import PolicySections from '@/components/disclosure/policy-sections';
import IconTile from '@/components/humetria/icon-tile';
import { Badge } from '@/components/ui/badge';
import { countOf } from '@/lib/plural';
import type { PolicyVersion } from '@/types';

/** The version published last, with its words and what it declares (pantalla Ajustes: a card with its fields). */
export default function PolicyLatest({ latest }: { latest: PolicyVersion }) {
    const complete = latest.covers.length === 3;

    return (
        <section
            aria-labelledby="policy-latest"
            className="grid gap-5 card p-5 md:p-6"
        >
            <header className="flex flex-wrap items-center gap-3">
                <IconTile icon={FileText} />
                <div className="min-w-0 flex-1">
                    <h2 id="policy-latest" className="text-lg font-semibold">
                        Versión {latest.version}
                    </h2>
                    <p className="text-meta text-muted-foreground">
                        Publicada el {latest.publishedOn} en{' '}
                        {countOf(latest.centers, 'centro', 'centros')}.
                    </p>
                </div>
                <Badge variant={complete ? 'success' : 'warning'}>
                    {complete
                        ? 'Declara a, b y c'
                        : `Declara ${latest.covers.join(', ') || 'ningún inciso'}`}
                </Badge>
            </header>
            <PolicySections sections={latest.sections} />
        </section>
    );
}
