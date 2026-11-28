# Cómo construir con el design system de Humetría

Estos son los componentes reales de la app de Humetría (`resources/js/components/ui` y `resources/js/components/humetria`).
**La app manda:** una pantalla nueva parte de la pantalla de la app más parecida y se arma con estas piezas; no se
inventan otras. Antes de diseñar, lee `guidelines/PRODUCT.md` (para quién y qué no debe parecer), `guidelines/DESIGN.md` (tokens y reglas)
y `guidelines/design-system.md` (marcos, piezas, «Composición y espacio», formularios y la app instalable).
`guidelines/propuestas-y-contradicciones.md` son **propuestas pendientes, no reglas**: no las des por hechas.

## Preparación

- Todo sale de `window.Humetria.*`. No hace falta un provider global; la página lleva `components/bundle.css`.
- **Tooltips:** todo lo que muestra un tooltip (`Tooltip`, `LevelBar`, `BarMeter`) va dentro de un `TooltipProvider`
  (la app pone uno en la raíz). Envuelve tu pantalla en uno.
- **Datos de la página:** los marcos, `CompanyMark`, `PlanLimitNotice`, `SupportLink` y `RouteHumiCard` leen lo que la
  app comparte en cada página (la empresa, el usuario y su rol, los módulos, la marca). Fuera de la app lo leen de
  `window.HumetriaPage = { url, props }`, definido antes de dibujar (la URL `/admin/<empresa>/…` enciende su elemento del
  menú; `brand: null` muestra el logo de Humetría):

```js
const can = Object.fromEntries(
    [
        'view_dashboard',
        'view_work_centers',
        'manage_organization',
        'manage_members',
        'view_worker_access',
        'manage_worker_access',
        'view_nom035_route',
        'manage_campaigns',
        'view_telework',
        'manage_telework',
        'view_seating',
        'manage_seating',
        'view_attendance',
        'manage_attendance',
        'handle_complaints',
        'manage_complaint_channel',
        'view_climate',
        'manage_climate',
        'view_pulse',
        'manage_pulse',
        'view_vacations',
        'manage_vacations',
        'view_org_chart',
        'manage_org_chart',
        'view_compensation',
        'manage_compensation',
        'view_interventions',
        'manage_interventions',
        'view_disclosure',
        'manage_disclosure',
        'use_assistant',
        'view_trauma_screenings',
        'review_workplaces',
        'assess_seating',
    ].map((k) => [k, true]),
);
window.HumetriaPage = {
    url: '/admin/aceros-del-norte/employees',
    props: {
        name: 'Humetría',
        support: { email: 'soporte@humetria.mx' },
        social: [],
        impersonator: null,
        demo: false,
        auth: {
            user: {
                id: 7,
                name: 'Laura Garza',
                email: 'laura.garza@empresa.mx',
            },
            isSuperadmin: false,
            isStaff: false,
        },
        tenant: {
            slug: 'aceros-del-norte',
            trade_name: 'Aceros del Norte',
            status: 'active',
            blocked: false,
            read_only: false,
            restriction_reason: null,
            type: 'company',
            role: 'owner',
            role_label: 'Dueña',
            trial_days_left: null,
            can,
            permissions: can,
            modules: [
                'nom035',
                'nom037',
                'seating',
                'attendance',
                'complaints',
                'engagement',
                'leave',
                'organization',
                'compensation',
            ],
        },
        tenants: {
            data: [
                { slug: 'aceros-del-norte', trade_name: 'Aceros del Norte' },
            ],
            total: 1,
        },
        inCourse: { count: 1, daysLeft: 9, elapsed: 64 },
        firstSteps: null,
        demoGuide: null,
        brand: null,
        sidebarOpen: true,
    },
};
```

- **Tema oscuro:** agrega la clase `dark` a un ancestro (`<html class="dark">`); los tokens cambian solos.
- Texto en español de México. El panel tutea («Envía un recordatorio»); la encuesta y el portal tratan de «usted».
- **Formularios:** diálogo para confirmar o 1–2 campos sobre algo existente; `FormSheet` para altas o ediciones de 3+
  campos; página propia para flujos largos. **Scroll:** uno por eje, el de la página; nada de cajas con scroll propio.
  Botones con verbo y objeto. Sin emoji ni signos de exclamación.

## Estilo: Tailwind con los tokens del sistema

Clases de utilidad de Tailwind v4 sobre tokens (variables CSS en `tokens.css`). **Solo existen las clases que la app ya
usa**: antes de usar una, búscala en `components/bundle.css`; si no está, usa `style` con la variable
(`style={{ color: 'var(--muted-foreground)' }}`). Nunca colores fijos (`#…`, `text-gray-500`).

| Para                | Clases                                                                                                                                                                                                                    |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Superficies         | `bg-background` (página), `bg-card` + `border` (tarjeta; o la utilidad `card`), `bg-muted` (cabecera de tabla, pistas), `bg-accent` (lo elegido, aviso suave)                                                             |
| Texto               | `text-foreground`, `text-muted-foreground` (secundario), `text-primary` (acento, enlaces), `text-destructive`                                                                                                             |
| Escala              | `text-3xl font-semibold` (título de página, lo hace `PageHeader`), `text-xl`/`text-lg font-semibold` (tarjeta), `text-base` (cuerpo), `text-meta` (datos bajo el título), `text-caption` (etiquetas y cabeceras de tabla) |
| Forma y profundidad | `rounded-xl` (tarjetas y diálogos), `rounded-lg`, `rounded-md` (controles); `shadow-raised` (panel de página), `shadow-float` (lo que flota)                                                                              |
| Riesgo y avisos     | `bg-risk-alto-bg text-risk-alto-fg` y demás `risk-*`, `bg-warning text-warning-foreground`. El riesgo se muestra siempre con `RiskLevel`.                                                                                 |
| Espacio             | `gap-2`/`gap-3`/`gap-4`/`gap-6`, `p-4`/`p-6`; formularios y lectura en `max-w-[880px]`, texto corrido en `max-w-[620px]`                                                                                                  |

Un solo acento (`primary`, jade). Cifras con `tabular-nums`. Figtree 400/500/600, ya cargada.

## Dónde está la verdad

- `components/bundle.css` (todas las clases), `tokens.css` y `tokens.json` (colores claro/oscuro con su contraste).
- `components/index.d.ts` (las props de cada componente) y `components/<Name>/README.md` (uso, con ejemplos).
- `guidelines/design-system.md` (marcos del panel, portal y consola; tabla de piezas), `guidelines/decisiones.md`,
  `guidelines/tokens-shadcn.md`.
- **Los marcos son componentes vivos:** una pantalla del panel va dentro de `AppSidebarLayout` (sidebar, barra superior
  con migas, panel de la página y barra inferior del celular; recibe `breadcrumbs`); una del portal, dentro de
  `WorkerFrame` (encabezado «Mi portal» o `back`, columna para una mano; `actions` fija la acción abajo) con
  `PortalTabBar` solo en el inicio. Ver `components/AppSidebarLayout/README.md` y `components/WorkerFrame/README.md`.

## Ejemplo

```tsx
const {
    TooltipProvider,
    PageHeader,
    Badge,
    Button,
    Notice,
    Card,
    CardContent,
    RiskLevel,
} = window.Humetria;

<TooltipProvider>
    <main className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
        <PageHeader
            title="Evaluación 2026"
            caption="NOM-035 · Planta Apodaca"
            status={<Badge>En curso</Badge>}
            description="142 de 186 personas contestaron. Cierra el 14 de diciembre."
        >
            <Button variant="outline">Enviar recordatorio</Button>
            <Button>Ver avance</Button>
        </PageHeader>
        <Notice
            action={
                <Button variant="outline" size="sm">
                    Ver quiénes
                </Button>
            }
        >
            <strong>3 personas</strong> no tienen correo ni celular.
        </Notice>
        <Card>
            <CardContent className="flex flex-col gap-3">
                <p className="text-caption text-muted-foreground uppercase">
                    Calificación final
                </p>
                <RiskLevel level="medium" label="Medio" size="lg" />
            </CardContent>
        </Card>
    </main>
</TooltipProvider>;
```

Lee el `README.md` de cada componente antes de usarlo (por ejemplo, `CountTabs` pide sus vistas con su conteo y su enlace).
