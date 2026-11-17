import { Form } from '@inertiajs/react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/humetria/input-error';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { ssoPayload } from '@/lib/sso';
import { update } from '@/routes/tenant/sso';
import type { SsoConnection, SsoDomain } from '@/types';

/** The identity provider and the domains of the company: what the owner copies from Entra, Google or Okta. */
export default function ConnectionForm({
    connection,
    domains,
}: {
    connection: SsoConnection | null;
    domains: SsoDomain[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...update.form(tenantSlug)}
            transform={ssoPayload}
            options={{ preserveScroll: true }}
            className="grid max-w-[620px] gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <FormField
                        name="issuer"
                        label="Dirección del proveedor (issuer)"
                        idPrefix="sso"
                        type="url"
                        placeholder="https://login.microsoftonline.com/…/v2.0"
                        defaultValue={connection?.issuer ?? ''}
                        error={errors['issuer']}
                        required
                    />
                    <FormField
                        name="client_id"
                        label="Client ID"
                        idPrefix="sso"
                        autoComplete="off"
                        defaultValue={connection?.client_id ?? ''}
                        error={errors['client_id']}
                        required
                    />
                    <FormField
                        name="client_secret"
                        label="Client secret"
                        idPrefix="sso"
                        type="password"
                        autoComplete="new-password"
                        placeholder={
                            connection?.has_secret === true
                                ? 'Guardado: déjalo en blanco para conservarlo'
                                : ''
                        }
                        error={errors['client_secret']}
                        required={connection === null}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="sso-domains">
                            Dominios de correo de tu empresa
                        </Label>
                        <Textarea
                            id="sso-domains"
                            name="domains_text"
                            rows={3}
                            placeholder="empresa.com.mx"
                            defaultValue={domains
                                .map((domain) => domain.domain)
                                .join('\n')}
                            aria-describedby="sso-domains-help"
                        />
                        <p
                            id="sso-domains-help"
                            className="text-sm text-muted-foreground"
                        >
                            Uno por línea. Solo entra quien tenga un correo de
                            estos dominios, y cada uno debe verificarse.
                        </p>
                        <InputError
                            message={errors['domains'] ?? errors['domains.0']}
                        />
                    </div>
                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar configuración
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
