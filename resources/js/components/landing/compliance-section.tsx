import SectionHeading from '@/components/landing/section-heading';
import CompliancePoints from '@/components/landing/compliance-points';

export default function ComplianceSection() {
    return (
        <section
            aria-labelledby="confidencialidad"
            className="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24"
        >
            <SectionHeading
                id="confidencialidad"
                title="Privacidad y apego a la norma"
            >
                La gente contesta con honestidad cuando sabe quién puede ver lo
                que contesta. Y tú presentas ante la STPS con la tranquilidad de
                haber seguido la norma y la ley.
            </SectionHeading>

            <div className="mt-10 grid gap-6 lg:grid-cols-2">
                <CompliancePoints group="privacy" />
                <CompliancePoints group="norm" />
            </div>
        </section>
    );
}
