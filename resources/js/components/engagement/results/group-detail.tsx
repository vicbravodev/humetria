import QuestionList from '@/components/engagement/results/question-list';
import BarMeter from '@/components/humetria/bar-meter';
import {
    favorableText,
    shareWidth,
    versusCompanyText,
} from '@/lib/engagement/format';
import type { EngagementGroup } from '@/types';

/** What one shown group answered: each driver as a bar against the company, and its strengths and opportunities. */
export default function GroupDetail({ group }: { group: EngagementGroup }) {
    return (
        <div className="grid gap-6 border-t bg-muted/40 p-5 lg:grid-cols-2 lg:gap-x-10">
            <ul className="flex flex-col gap-3">
                {group.drivers.map((driver, index) => (
                    <li key={driver.key} className="grid gap-1">
                        <span className="flex flex-wrap items-baseline justify-between gap-x-4 text-sm">
                            {driver.label}
                            <span className="text-muted-foreground">
                                {driver.descriptive
                                    ? 'Pregunta por pregunta'
                                    : `${favorableText(driver.favorable)} ${versusCompanyText(driver.vsCompany)}`}
                            </span>
                        </span>
                        {!driver.descriptive && (
                            <BarMeter
                                percent={shareWidth(driver.favorable)}
                                tooltip={`${driver.label}: ${favorableText(driver.favorable)}`}
                                index={index}
                            />
                        )}
                    </li>
                ))}
            </ul>
            <div className="flex flex-col gap-6">
                <QuestionList heading="Fortalezas" items={group.strengths} />
                <QuestionList
                    heading="Oportunidades"
                    items={group.opportunities}
                />
            </div>
        </div>
    );
}
