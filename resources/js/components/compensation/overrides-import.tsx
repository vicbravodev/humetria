import CsvImportCard from '@/components/compensation/csv-import-card';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { importMethod, template } from '@/routes/tenant/compensation/overrides';

/** «Importar sueldos individuales»: what many people earn at once, each by their company ID. */
export default function OverridesImport({ maxRows }: { maxRows: number }) {
    const tenantSlug = useTenantSlug();

    return (
        <CsvImportCard
            form={importMethod.form(tenantSlug)}
            templateUrl={template.url(tenantSlug)}
            title="Importar sueldos individuales desde un CSV"
            columns="id_de_la_empresa, salario_diario, vigencia"
        >
            {`Hasta ${maxRows.toLocaleString('es-MX')} filas, una por persona, con su ID tal como está en el padrón. Puedes agregar una columna «nota». Un sueldo con la misma vigencia se reemplaza y los anteriores se conservan. Se importa completo o nada.`}
        </CsvImportCard>
    );
}
