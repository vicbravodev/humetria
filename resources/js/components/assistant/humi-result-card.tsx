import HumiListCard from '@/components/assistant/humi-list-card';
import HumiProgressCard from '@/components/assistant/humi-progress-card';
import HumiRiskCard from '@/components/assistant/humi-risk-card';
import HumiStatusCard from '@/components/assistant/humi-status-card';
import type { ToolCard } from '@/lib/assistant/tool-cards';

/** The card a tool answered, drawn as the one it asks for: risk levels, progress, norm status or a list. */
export default function HumiResultCard({ card }: { card: ToolCard }) {
    switch (card.render) {
        case 'risk_levels':
            return <HumiRiskCard card={card} />;
        case 'progress':
            return <HumiProgressCard card={card} />;
        case 'status_list':
            return <HumiStatusCard card={card} />;
        case 'list':
            return <HumiListCard card={card} />;
    }
}
