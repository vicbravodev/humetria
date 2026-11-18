import { Form } from '@inertiajs/react';
import { toast } from 'sonner';
import DemoCaseDetails from '@/components/landing/demo-case-details';
import DemoHoneypot from '@/components/landing/demo-honeypot';
import DemoPrivacyNote from '@/components/landing/demo-privacy-note';
import DemoTextField from '@/components/landing/demo-text-field';
import PhoneField from '@/components/phone-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { track, trackFormErrors, trackLead } from '@/lib/analytics';
import { firstInvalidField } from '@/lib/first-invalid-field';
import { httpErrorMessage } from '@/lib/http-error-message';
import { store } from '@/routes/demo-requests';

type Props = {
    /** The route of the page the form is on: the server sends whoever asked back to it. */
    page?: string | undefined;
    /** The tool of that page, already chosen. */
    tool?: string | undefined;
};

export default function DemoRequestForm({ page = 'home', tool }: Props) {
    return (
        <Form
            {...store.form()}
            noValidate
            resetOnSuccess
            id="demo-request-form"
            onBefore={() => track('demo_form_submitted')}
            onSuccess={(page) => trackLead(sessionStorage, page.flash.lead?.id)}
            onError={(errors) => {
                trackFormErrors(errors);
                const fields = document
                    .getElementById('demo-request-form')
                    ?.querySelectorAll<HTMLInputElement>('[name]');

                const field = firstInvalidField(fields ?? [], errors);

                // A phone sends a hidden field: what takes the focus is the box it is typed in.
                (field?.type === 'hidden'
                    ? field.parentElement?.querySelector<HTMLInputElement>(
                          'input[type=tel]',
                      )
                    : field
                )?.focus();
            }}
            onHttpException={(response) => {
                const message = httpErrorMessage(response);

                if (message !== null) {
                    toast.error(message);
                }

                return message === null;
            }}
            className="relative grid gap-[18px] rounded-3xl border bg-card p-5 shadow-raised sm:p-8"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-[18px] sm:grid-cols-2">
                        <DemoTextField
                            name="name"
                            label="Nombre"
                            autoComplete="name"
                            required
                            maxLength={120}
                            error={errors['name']}
                        />
                        <DemoTextField
                            name="email"
                            type="email"
                            label="Correo electrónico"
                            autoComplete="email"
                            placeholder="nombre@empresa.mx"
                            required
                            maxLength={190}
                            error={errors['email']}
                        />
                        <DemoTextField
                            name="company"
                            label="Empresa"
                            autoComplete="organization"
                            required
                            maxLength={160}
                            error={errors['company']}
                        />
                        <PhoneField
                            name="whatsapp"
                            idPrefix="demo"
                            label="WhatsApp"
                            required
                            className="h-13 bg-background"
                            error={errors['whatsapp']}
                        />
                    </div>
                    <DemoCaseDetails errors={errors} tool={tool} />
                    <DemoHoneypot />
                    <input type="hidden" name="page" value={page} />
                    <Button
                        type="submit"
                        size="lg"
                        className="h-13 text-base"
                        disabled={processing}
                        data-test="demo-request-submit"
                    >
                        {processing && <Spinner />}
                        Agenda mi demo
                    </Button>
                    <DemoPrivacyNote />
                </>
            )}
        </Form>
    );
}
