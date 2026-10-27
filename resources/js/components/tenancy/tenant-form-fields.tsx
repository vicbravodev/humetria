import { useState } from 'react';
import TenantField from '@/components/tenancy/tenant-field';
import { slugify } from '@/lib/slug';

/** The four fields of a company. The address follows the trade name until the person types in it. */
export default function TenantFormFields({
    errors,
}: {
    errors: Partial<Record<string, string>>;
}) {
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);

    return (
        <div className="grid gap-4">
            <TenantField
                name="legal_name"
                label="Razón social"
                required
                maxLength={200}
                error={errors['legal_name']}
            />
            <TenantField
                name="trade_name"
                label="Nombre comercial"
                required
                maxLength={120}
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
        </div>
    );
}
