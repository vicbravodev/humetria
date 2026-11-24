import HumiChoicesCard from '@/components/assistant/humi-choices-card';
import HumiListCard from '@/components/assistant/humi-list-card';
import HumiProgressCard from '@/components/assistant/humi-progress-card';
import HumiRiskCard from '@/components/assistant/humi-risk-card';
import HumiStatusCard from '@/components/assistant/humi-status-card';
import type { ChoiceState } from '@/lib/assistant/chosen-choice';
import type { ToolCard } from '@/lib/assistant/tool-cards';

/** The card a tool answered, drawn as the one it asks for: risk levels, progress, norm status, a list or the buttons of a question. */
export default function HumiResultCard({
    card,
    choice,
}: {
    card: ToolCard;
    choice: ChoiceState;
}) {
    switch (card.render) {
        case 'risk_levels':
            return <HumiRiskCard card={card} />;
        case 'progress':
            return <HumiProgressCard card={card} />;
        case 'status_list':
            return <HumiStatusCard card={card} />;
        case 'list':
            return <HumiListCard card={card} />;
        case 'choices':
            return <HumiChoicesCard card={card} choice={choice} />;
    }
}
