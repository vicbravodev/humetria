import Notice from '@/components/humetria/notice';
import { coverageMessage } from '@/lib/telework/coverage';
import { sectionTitle } from '@/lib/telework/checklist';

/** What each section leaves without a question, before publishing: the inspection checks those requirements. */
export default function CoverageWarnings({
    labels,
}: {
    /** Section number to the labels of the requirements it has no question for. */
    labels: Record<string, string[]>;
}) {
    return Object.entries(labels).map(([section, list]) => {
        const message = coverageMessage(list);

        return message === null ? null : (
            <Notice key={section}>
                <strong>{sectionTitle(Number(section))}.</strong> {message}
            </Notice>
        );
    });
}
