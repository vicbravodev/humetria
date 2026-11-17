import { agreementSections } from '@/lib/attendance/punch-agreement';

/**
 * The agreement on the electronic record, whole (it has the value of proof: nothing is summed up nor hidden), as a list of
 * sections with their titles in a card that does not scroll on its own (board «Asistencia: acuerdo de registro»).
 */
export default function AgreementCard({ body }: { body: string }) {
    return (
        <div className="flex flex-col divide-y card px-4">
            {agreementSections(body).map(({ title, text }) => (
                <section key={title + text} className="py-4">
                    {title !== '' && (
                        <h2 className="mb-1 text-[17px] leading-[26px] font-semibold">
                            {title}
                        </h2>
                    )}
                    <p className="text-[17px] leading-[26px]">{text}</p>
                </section>
            ))}
        </div>
    );
}
