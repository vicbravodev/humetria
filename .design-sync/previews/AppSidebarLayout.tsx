import { AppSidebarLayout, Badge, Button, Card, CountTabs, PageHeader, Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TooltipProvider } from 'humetria';
import { Building2, Upload, Users } from 'lucide-react';

// What the app shares through Inertia (usePage()); outside the app the bundle reads it from here.
const allowed = Object.fromEntries(
    [
        'manage_organization', 'manage_members', 'manage_campaigns', 'view_trauma_screenings', 'manage_telework',
        'review_workplaces', 'view_seating', 'view_attendance', 'manage_attendance', 'view_org_chart', 'manage_org_chart',
        'view_vacations', 'manage_vacations', 'view_climate', 'manage_climate', 'view_pulse', 'manage_pulse',
        'manage_seating', 'assess_seating', 'view_compensation', 'manage_compensation', 'view_interventions',
        'manage_interventions', 'view_disclosure', 'manage_disclosure', 'use_assistant', 'manage_complaint_channel',
        'handle_complaints', 'view_worker_access', 'manage_worker_access', 'view_dashboard', 'view_work_centers',
        'view_nom035_route', 'view_telework',
    ].map((key) => [key, true]),
);

Object.assign(globalThis, {
    HumetriaPage: {
        url: '/admin/aceros-del-norte/employees',
        props: {
            name: 'Humetría',
            support: { email: 'soporte@humetria.mx' },
            social: [],
            auth: {
                user: {
                    id: 7,
                    name: 'Laura Garza Treviño',
                    email: 'laura.garza@empresa.mx',
                    email_verified_at: '2026-10-02T15:00:00Z',
                    created_at: '2026-10-02T15:00:00Z',
                    updated_at: '2026-11-20T15:00:00Z',
                },
                isSuperadmin: false,
                isStaff: false,
            },
            impersonator: null,
            tenant: {
                slug: 'aceros-del-norte',
                trade_name: 'Aceros del Norte',
                status: 'active',
                blocked: false,
                read_only: false,
                restriction_reason: null,
                type: 'company',
                modules: ['nom035', 'nom037', 'seating', 'attendance', 'complaints', 'engagement', 'leave', 'organization', 'compensation'],
                trial_days_left: null,
                role: 'owner',
                role_label: 'Dueña',
                can: allowed,
                permissions: allowed,
            },
            tenants: { data: [{ slug: 'aceros-del-norte', trade_name: 'Aceros del Norte' }], total: 1 },
            inCourse: { count: 1, daysLeft: 9, elapsed: 64 },
            firstSteps: null,
            demoGuide: null,
            demo: false,
            brand: null,
            sidebarOpen: true,
        },
    },
});

// Each story sets whether the sidebar starts open: AppShell reads `sidebarOpen` once, when it mounts.
const sidebarOpen = (open: boolean) => {
    (globalThis as unknown as { HumetriaPage: { props: { sidebarOpen: boolean } } }).HumetriaPage.props.sidebarOpen = open;
};

const breadcrumbs = [
    { title: 'Aceros del Norte', href: '/admin/aceros-del-norte' },
    { title: 'Empleados', href: '/admin/aceros-del-norte/employees' },
];

const people = [
    { name: 'Mariana López Treviño', area: 'Producción', center: 'Planta Apodaca', status: 'Invitada', variant: 'success' as const },
    { name: 'Jorge Salinas Ruiz', area: 'Almacén', center: 'Planta Apodaca', status: 'Sin invitar', variant: 'secondary' as const },
    { name: 'Ana Cecilia Villarreal', area: 'Recursos Humanos', center: 'Oficinas Monterrey', status: 'Sin contacto', variant: 'warning' as const },
    { name: 'Rodolfo Cantú Elizondo', area: 'Mantenimiento', center: 'Planta Apodaca', status: 'Invitado', variant: 'success' as const },
    { name: 'Diana Ramírez Ochoa', area: 'Calidad', center: 'Planta Escobedo', status: 'Invitada', variant: 'success' as const },
];

const EmployeesPage = () => (
    <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
        <PageHeader
            title="Empleados"
            facts={[
                { icon: Users, text: '214 personas' },
                { icon: Building2, text: '3 centros de trabajo' },
            ]}
        >
            <Button variant="outline">
                <Upload className="size-4" />
                Importar CSV
            </Button>
            <Button>Agregar empleado</Button>
        </PageHeader>
        <CountTabs
            label="Estado de las personas"
            tabs={[
                { label: 'Activas', count: 214, href: '#activas', active: true },
                { label: 'Sin contacto', count: 6, href: '#sin-contacto', active: false },
                { label: 'Bajas', count: 12, href: '#bajas', active: false },
            ]}
        />
        <Card className="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Centro de trabajo</TableHead>
                        <TableHead>Acceso</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.map((p) => (
                        <TableRow key={p.name}>
                            <TableCell>
                                <div className="font-medium">{p.name}</div>
                                <div className="text-sm text-muted-foreground">{p.area}</div>
                            </TableCell>
                            <TableCell>{p.center}</TableCell>
                            <TableCell>
                                <Badge variant={p.variant}>{p.status}</Badge>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </Card>
    </div>
);

export const EmployeesPanel = () => {
    sidebarOpen(true);
    return (
        <TooltipProvider>
            <AppSidebarLayout breadcrumbs={breadcrumbs}>
                <EmployeesPage />
            </AppSidebarLayout>
        </TooltipProvider>
    );
};

export const SidebarCollapsed = () => {
    sidebarOpen(false);
    return (
        <TooltipProvider>
            <AppSidebarLayout breadcrumbs={breadcrumbs}>
                <EmployeesPage />
            </AppSidebarLayout>
        </TooltipProvider>
    );
};
