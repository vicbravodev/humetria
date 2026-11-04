import CsvImportCard from '@/components/compensation/csv-import-card';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { importMethod, template } from '@/routes/tenant/compensation/tabulator';

/** «Importar el tabulador»: the salary of many categories and levels at once. */
export default function TabulatorImport({ maxRows }: { maxRows: number }) {
    const tenantSlug = useTenantSlug();

    return (
        <CsvImportCard
            form={importMethod.form(tenantSlug)}
            templateUrl={template.url(tenantSlug)}
            title="Importar el tabulador desde un CSV"
            columns="categoria, nivel, salario_diario, vigencia"
        >
            {`Hasta ${maxRows.toLocaleString('es-MX')} filas. La categoría y el nivel que no existan se crean, y un salario con la misma vigencia se reemplaza. Se importa completo o nada.`}
        </CsvImportCard>
    );
}
