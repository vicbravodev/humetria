import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import PersonShell from '@/components/disclosure/person-shell';
import PolicySections from '@/components/disclosure/policy-sections';
import PortalConfirm from '@/components/disclosure/portal-confirm';
import type { PortalPolicyProps } from '@/types';

export default function PortalPolicy({
    token,
    company,
    name,
    policy,
    acknowledgment,
}: PortalPolicyProps) {
    return (
        <PersonShell company={company} label="Personal">
            <Head title="La política de prevención" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        La política de prevención, {name}
                    </h1>
                    <p className="text-muted-foreground">
                        {policy === null
                            ? 'Su centro de trabajo todavía no publica su política de prevención de riesgos psicosociales.'
                            : `Es la versión ${policy.version}, publicada el ${policy.publishedOn}: lo que ${company} se compromete a hacer para prevenir los riesgos psicosociales y la violencia laboral y para promover un entorno favorable.`}
                    </p>
                </div>
                {policy !== null && (
                    <>
                        <section
                            aria-label="La política"
                            className="flex flex-col gap-4 card p-5"
                        >
                            <FileText
                                aria-hidden
                                className="size-6 text-primary"
                            />
                            <PolicySections
                                sections={policy.sections}
                                tone="person"
                            />
                        </section>
                        {acknowledgment !== null && (
                            <PortalConfirm
                                token={token}
                                acknowledgment={acknowledgment}
                            />
                        )}
                    </>
                )}
            </div>
        </PersonShell>
    );
}
