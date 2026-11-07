import { FileText } from 'lucide-react';
import { agreementSections } from '@/lib/attendance/enroll';

/** The agreement on the electronic record the person reads before linking their device, a section per paragraph. */
export default function AgreementCard({
    version,
    body,
}: {
    version: number;
    body: string;
}) {
    return (
        <section
            aria-labelledby="agreement-title"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex items-center gap-3">
                <FileText
                    aria-hidden
                    className="size-6 shrink-0 text-primary"
                />
                <h2
                    id="agreement-title"
                    className="text-xl leading-7 font-semibold"
                >
                    Acuerdo de registro electrónico
                </h2>
            </div>
            <p className="text-base text-muted-foreground">Versión {version}</p>
            {agreementSections(body).map(({ title, text }) => (
                <div key={title + text} className="flex flex-col gap-1">
                    {title !== '' && (
                        <h3 className="text-base font-semibold">{title}</h3>
                    )}
                    <p className="text-base leading-6">{text}</p>
                </div>
            ))}
        </section>
    );
}
