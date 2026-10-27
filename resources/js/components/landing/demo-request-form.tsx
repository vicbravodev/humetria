import { Form } from '@inertiajs/react';
import { toast } from 'sonner';
import type { DemoChoice } from '@/components/landing/demo-choice-field';
import DemoChoiceField from '@/components/landing/demo-choice-field';
import DemoHoneypot from '@/components/landing/demo-honeypot';
import DemoMessageField from '@/components/landing/demo-message-field';
import DemoTextField from '@/components/landing/demo-text-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { tooManyRequestsMessage } from '@/lib/too-many-requests';
import { store } from '@/routes/demo-requests';

const PROFILES: readonly DemoChoice[] = [
    { value: 'company', label: 'Aplico la NOM-035 en mi empresa' },
    { value: 'consultant', label: 'Soy consultor' },
];

const WORKFORCE: readonly DemoChoice[] = [
    { value: '1_15', label: '1–15' },
    { value: '16_50', label: '16–50' },
    { value: '51_100', label: '51–100' },
    { value: '101_500', label: '101–500' },
    { value: '501_plus', label: 'Más de 500' },
];

export default function DemoRequestForm() {
    return (
        <Form
            {...store.form()}
            noValidate
            resetOnSuccess
            onHttpException={(response) => {
                const message = tooManyRequestsMessage(response);

                if (message !== null) {
                    toast.error(message);
                }

                return message === null;
            }}
            className="relative grid gap-6 rounded-lg border border-border bg-card p-4 text-left sm:p-8"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-6 sm:grid-cols-2">
                        <DemoTextField
                            name="name"
                            label="Nombre"
                            autoComplete="name"
                            required
                            maxLength={120}
                            error={errors['name']}
                        />
                        <DemoTextField
                            name="company"
                            label="Empresa"
                            autoComplete="organization"
                            required
                            maxLength={160}
                            error={errors['company']}
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
                            name="whatsapp"
                            type="tel"
                            label="WhatsApp"
                            autoComplete="tel-national"
                            inputMode="tel"
                            placeholder="55 1234 5678"
                            required
                            error={errors['whatsapp']}
                        />
                    </div>
                    <DemoChoiceField
                        name="profile"
                        legend="¿Cómo aplicas la NOM-035?"
                        options={PROFILES}
                        error={errors['profile']}
                        className="sm:grid-cols-2"
                    />
                    <DemoChoiceField
                        name="workforce"
                        legend="¿Cuántas personas trabajan en la empresa?"
                        options={WORKFORCE}
                        error={errors['workforce']}
                        className="grid-cols-2 sm:grid-cols-3"
                    />
                    <DemoMessageField error={errors['message']} />
                    <DemoHoneypot />
                    <Button
                        type="submit"
                        size="lg"
                        disabled={processing}
                        data-test="demo-request-submit"
                        className="justify-self-start"
                    >
                        {processing && <Spinner />}
                        Agendar mi demo
                    </Button>
                </>
            )}
        </Form>
    );
}
