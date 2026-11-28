import { MobileTabBar, SidebarProvider } from 'humetria';

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
        url: '/admin/aceros-del-norte',
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

// The bar floats fixed at the bottom of a phone-width viewport (it hides from md up); Panel is where the person is.
export const OwnerOnPanel = () => (
    <SidebarProvider defaultOpen>
        {/* `transform` makes this box the containing block of the fixed bar, so it floats at the foot of the card. The card
            leaves ~340 px, narrower than a phone: the box is a 375 px phone drawn at 90 % so the five tabs fit as they do. */}
        <div className="relative bg-background p-4" style={{ width: 375, height: 280, zoom: 0.9, transform: 'translateZ(0)' }}>
            <p className="text-2xl font-semibold">Panel</p>
            <p className="text-muted-foreground">Aceros del Norte · 214 personas</p>
            <MobileTabBar />
        </div>
    </SidebarProvider>
);
