import { contactFacts } from '@/lib/company-360';
import type { CompanyDetail, CompanyPayer, ConsolePlan } from '@/types';

/** Who to talk to about the company, who pays it, when it renews and when anyone last came in, at the side of its page. */
export default function CompanyContactCard({
    company,
    plan,
    payer,
}: {
    company: CompanyDetail;
    plan: ConsolePlan | null;
    payer: CompanyPayer | null;
}) {
    const facts = contactFacts(company, plan?.renews_on ?? null, payer);

    return (
        <section aria-labelledby="company-contact" className="card">
            <h2
                id="company-contact"
                className="border-b px-6 py-4 text-base font-semibold"
            >
                Cuenta y contacto
            </h2>
            <dl className="divide-y">
                {facts.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-center justify-between gap-3 px-6 py-3.5"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-right font-medium [overflow-wrap:anywhere]">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
