import { companyFacts } from '@/lib/tenant-work';
import type { CompanyDetail, ConsolePlan } from '@/types';

/** Who to talk to about the company and where it stands with Humetría, at the side of its page. */
export default function CompanyContactCard({
    company,
    plan,
}: {
    company: CompanyDetail;
    plan: ConsolePlan | null;
}) {
    const facts = companyFacts(company, plan);

    return (
        <section aria-labelledby="company-contact" className="card">
            <h2
                id="company-contact"
                className="border-b px-5 py-4 text-base font-semibold"
            >
                Cuenta y contacto
            </h2>
            <dl className="grid gap-3 px-5 py-4 text-sm">
                {facts.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4">
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
