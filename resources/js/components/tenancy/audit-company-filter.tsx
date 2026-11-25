import Combobox from '@/components/humetria/combobox';
import { Label } from '@/components/ui/label';
import { serverSource } from '@/lib/server-options';
import { companies } from '@/routes/audit';

const ALL_COMPANIES = { value: '', label: 'Todas las empresas' };

/** Narrows the trail to one company, searched on the server; «Todas las empresas» undoes it. */
export default function AuditCompanyFilter({
    company,
    onChange,
}: {
    company: { id: string; name: string } | null;
    onChange: (company: string) => void;
}) {
    return (
        <div className="grid w-full gap-1.5 md:w-60">
            <Label htmlFor="audit-company">Empresa</Label>
            <Combobox
                id="audit-company"
                name="company"
                source={serverSource(companies.url(), [ALL_COMPANIES])}
                defaultValue={
                    company === null
                        ? ALL_COMPANIES
                        : { value: company.id, label: company.name }
                }
                placeholder="Empresa"
                onChange={(option) => onChange(option.value)}
            />
        </div>
    );
}
