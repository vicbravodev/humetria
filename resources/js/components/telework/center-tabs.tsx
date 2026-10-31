import CountTabs from '@/components/humetria/count-tabs';
import type { CenterTab } from '@/types';

/** The views of a center with how many each holds (Padrón 3 · Documentos 4 · Avisos 1); only those the person can open. */
export default function CenterTabs({ tabs }: { tabs: CenterTab[] }) {
    return <CountTabs label="Secciones del centro" tabs={tabs} />;
}
