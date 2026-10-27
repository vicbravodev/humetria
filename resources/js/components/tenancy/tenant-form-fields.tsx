import { useState } from 'react';
import TenantField from '@/components/tenancy/tenant-field';
import { slugify } from '@/lib/slug';
import type { TenantRow } from '@/types';

/**
 * The four fields of a company. When creating, the address follows the trade name until the person types in it;
 * when editing, it never follows it, and changing it warns that the old addresses stop working.
 */
export default function TenantFormFields({
    errors,
    tenant,
}: {
    errors: Partial<Record<string, string>>;
    tenant?: TenantRow;
}) {
    const [slug, setSlug] = useState(tenant?.slug ?? '');
    const [slugTouched, setSlugTouched] = useState(tenant !== undefined);

    return (
        <div className="grid gap-4">
            <TenantField
                name="legal_name"
                label="Razón social"
                required
                maxLength={200}
                defaultValue={tenant?.legal_name}
                error={errors['legal_name']}
            />
            <TenantField
                name="trade_name"
                label="Nombre comercial"
                required
                maxLength={120}
                defaultValue={tenant?.trade_name}
                error={errors['trade_name']}
                onChange={(event) => {
                    if (!slugTouched) {
                        setSlug(slugify(event.target.value));
                    }
                }}
            />
            <TenantField
                name="rfc"
                label="RFC"
                required
                maxLength={14}
                defaultValue={tenant?.rfc}
                autoCapitalize="characters"
                error={errors['rfc']}
            />
            <TenantField
                name="slug"
                label="Dirección"
                prefix="/admin/"
                required
                maxLength={50}
                value={slug}
                onChange={(event) => {
                    setSlugTouched(true);
                    setSlug(event.target.value);
                }}
                error={errors['slug']}
            />
            {tenant !== undefined && slug !== tenant.slug && (
                <p className="text-sm text-muted-foreground">
                    Las direcciones anteriores de esta empresa dejarán de
                    funcionar.
                </p>
            )}
        </div>
    );
}
