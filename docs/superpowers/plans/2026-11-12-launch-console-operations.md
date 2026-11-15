# F7 · Consola: operar la plataforma sin terminal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el superadmin pueda consultar la bitácora, ayudar a un usuario que no entra, trabajar las solicitudes de demo y avisar a sus clientes, sin abrir una terminal ni la base de datos; y que el owner vea qué plan tiene.

**Architecture:** Páginas nuevas en la sección «Plataforma» de la consola, dentro del módulo Tenancy (las rutas van en `routes/tenancy.php`; no se crea un archivo de rutas nuevo). La bitácora y los usuarios son kernel compartido (`App\Models\AuditEntry`, `App\Models\User`) y se leen directo; Demos y Billing se leen por contratos. Toda acción nueva de plataforma va a la bitácora con `App\Actions\RecordAudit` y queda prohibida mientras se impersona.

**Tech Stack:** Laravel 13 (Fortify para el restablecimiento), Spatie Permission (roles de plataforma), Pest, Inertia + React, Postgres. Sin dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (SA-03, SA-04, SA-05 y gaps de plataforma y de cliente; decisiones 10, 11, 12, 13, 15, 16) · Specs: `2026-11-09-monetization-admin-design.md` (§4.1 matriz de roles, Fase 3.2), `2026-10-27-tenancy-create-company-design.md` («Logs y bitácora»).

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario. PHPStan `max` y TS strictest sin supresiones ni `as`.
- **`audit_entries` es de solo inserción**: el visor solo lee. No existe ruta que edite o borre.
- La bitácora nunca contiene respuestas de encuesta (lo garantiza `RedactSensitiveData`); el visor muestra el contexto tal como está guardado, sin enriquecerlo con datos de otras tablas.
- Roles con Spatie Permission, sin sistemas propios. Crear un superadmin sigue siendo solo por CLI (decisión 12).
- Toda lista se pagina en el servidor (`->paginate()->withQueryString()`, `Paginated<T>`), con búsqueda `SearchesText`; cada página nueva entra a `tests/Feature/PaginatedListsTest.php` y tiene su `visitPage()` en `tests/Browser`.
- Toda ruta con nombre y autenticada; las de plataforma entran a `ForbidWhileImpersonating::protectedRoutes()` con su test.
- Tenancy no importa modelos de Demos ni de Billing: solo `Contracts` y `Data`. Un módulo de frontend no importa componentes de otro.
- El cliente no ve importes (decisión 15): la página de plan no trae precios ni estados de cuenta.
- Migraciones con prefijo `2027_01_12_*`; `timestampTz`; `CHECK` en la base para los estados.
- Correos por canales de `Notification`, en cola; cada correo nuevo entra a `tests/Feature/MailInventoryTest.php`.
- Referencias de diseño: la consola de empresas de `docs/design-system/pantallas/` (marco, encabezado, pestañas con conteo, tabla con cabecera `muted`, estados vacíos con siguiente paso) y `docs/rediseno/pantallas/png/V3-Estados.png`. Una pantalla que se vea como tabla sin diseñar no está terminada. Claro, oscuro y 375 px.
- Rama `feature/launch-console-operations`. Empieza con F6 en `main`. Único frente que toca `resources/js/lib/staff-navigation.ts`.

## Review Focus

1. **Bitácora con cientos de miles de entradas.** Abre en una página, filtra por evento, empresa y fechas usando índices, y no hace una consulta por fila. → Task 1.
2. **Un usuario de soporte (no superadmin) pide la bitácora o intenta quitar un 2FA.** 403 en ambos, y el intento no cambia nada. → Task 1, Task 2.
3. **Quitar el 2FA al propio superadmin o a otro superadmin.** No se permite desde la consola. → Task 2.
4. **Dos superadmins convierten la misma solicitud de demo.** Una sola empresa queda ligada; el segundo ve que ya está convertida. → Task 3.
5. **Empresa suspendida cuyo owner no tiene correo verificado, o que no tiene owner.** La suspensión se aplica igual y queda registrado que no hubo a quién avisar. → Task 4.

---

### Task 1: Visor de bitácora (SA-03)

**Files:**
- Create: `app/Modules/Tenancy/Http/Controllers/AuditConsoleController.php`, `app/Modules/Tenancy/Support/AuditPages.php`, ruta `GET admin/audit` en `routes/tenancy.php`
- Create: `resources/js/pages/tenancy/audit.tsx`, `resources/js/components/tenancy/audit-toolbar.tsx`, `audit-table.tsx`, `audit-row.tsx`, `resources/js/lib/audit.ts`
- Modify: `resources/js/lib/staff-navigation.ts` («Bitácora»), `resources/js/pages/tenancy/company.tsx` (enlace «Ver su bitácora» con el filtro de esa empresa), `app/Http/Middleware/ForbidWhileImpersonating.php:28-41`
- Test: `tests/Feature/Tenancy/AuditConsoleTest.php` (nuevo), `tests/Feature/PaginatedListsTest.php`, `tests/Unit/PaginatedListsTest.php` (`families`: «catálogo fijo de familias de evento»), `tests/js/lib/audit.test.ts`, `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Página `tenancy/audit` con `entries: Paginated<{id, at, event, actor, impersonator, company, subject, context}>` (50 por página, más reciente primero), `filters: {family, company, from, to, q}`, `families: list<{value, label}>`.
- `family` filtra por prefijo de evento (`tenant`, `billing`, `impersonation`, `user`, `campaign`, …); `company` por id; `from`/`to` por día local; `q` por correo de quien actuó.
- `eventLabel(event: string): string` en `lib/audit.ts`: nombre legible del evento, con el nombre crudo como respaldo.

- [ ] **Step 1: Tests que fallan** en `AuditConsoleTest.php`:
  - superadmin ve la lista paginada, más reciente primero; soporte y owner → 403; sin sesión → login;
  - cada filtro (familia, empresa, rango de fechas con sus dos bordes, correo) devuelve solo lo suyo;
  - una entrada hecha impersonando muestra a quien impersonaba;
  - la página fija su número de consultas con `expectsDatabaseQueryCount` (sin N+1 por actor o empresa);
  - no existen rutas `PUT`, `PATCH` ni `DELETE` bajo `admin/audit`;
  - impersonando → la ruta está prohibida.
- [ ] **Step 2:** Correr → 404.
- [ ] **Step 3:** Backend. Si los filtros no tienen índice, agregar la migración `2027_01_12_090000_add_audit_entries_indexes.php` (`created_at`, `tenant_id`), que es compatible con el trigger de solo inserción.
- [ ] **Step 4:** Página: encabezado con título y conteo, barra de filtros, tabla con fecha y hora (`display_timezone`), evento, quién, empresa y un detalle plegable con el contexto como pares llave-valor. Estado vacío «Nada coincide con estos filtros» con «Quitar filtros».
- [ ] **Step 5:** `visitPage('tenancy/audit', …)`; claro, oscuro y 375 px (en celular: fecha, evento y quién; el resto en el detalle).
- [ ] **Step 6: Commit** `Consola: visor de bitácora con filtros por evento, empresa, fecha y persona`.

### Task 2: Usuarios de la plataforma (SA-04)

**Files:**
- Create: `app/Modules/Tenancy/Http/Controllers/UserConsoleController.php` (`index`, `sendReset`, `removeTwoFactor`, `grantSupport`, `revokeSupport`), `app/Modules/Tenancy/Actions/RemoveTwoFactor.php`, `SendPasswordReset.php`, `app/Modules/Tenancy/Support/UserPages.php`, rutas en `routes/tenancy.php`
- Create: `resources/js/pages/tenancy/users.tsx`, `resources/js/components/tenancy/users-table.tsx`, `user-row.tsx`, `user-row-actions.tsx`
- Modify: `app/Console/Commands/GrantSupport.php` (delegar en una acción compartida `App\Actions\SetSupportRole` para no duplicar), `resources/js/lib/staff-navigation.ts` («Usuarios»), `ForbidWhileImpersonating.php`
- Test: `tests/Feature/Tenancy/UserConsoleTest.php` (nuevo), `tests/Feature/Auth/SupportRoleTest.php`, `tests/Feature/PaginatedListsTest.php`, `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Página `tenancy/users` con `users: Paginated<{id, name, email, platformRole, companies: string, twoFactor: bool, verified: bool}>` y `filters: {q}`. `companies` es un texto corto («Acme (owner), Beta (hr)»), no un arreglo.
- `SendPasswordReset::handle(User $user): void` usa `Password::broker()->sendResetLink()`. `RemoveTwoFactor::handle(User $user): void` deja en `null` `two_factor_secret`, `two_factor_recovery_codes` y `two_factor_confirmed_at`.
- Eventos de bitácora: `user.password_reset_sent`, `user.two_factor_removed`, `user.support_granted`, `user.support_revoked`.

- [ ] **Step 1: Tests que fallan.**
  - Buscar por correo y por nombre (sin acentos, palabras en cualquier orden); paginado.
  - «Enviar enlace para restablecer contraseña» → notificación de Fortify a ese usuario y entrada en la bitácora, sin el token.
  - «Quitar verificación en dos pasos» → columnas en `null`, bitácora; a un superadmin (uno mismo u otro) → 403 y nada cambia.
  - Dar y quitar soporte; quitarse el propio rol → 403.
  - Soporte y owner → 403 en todo; impersonando → prohibido.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** Cada acción pide confirmación (`confirm-dialog`) que nombra a la persona. `visitPage('tenancy/users', …)`; claro, oscuro, 375 px.
- [ ] **Step 6: Commit** (tres): lista y búsqueda · restablecer y quitar 2FA · dar y quitar soporte.

### Task 3: Solicitudes de demo que se pueden trabajar (SA-05)

**Files:**
- Create: `database/migrations/2027_01_12_100000_add_pipeline_to_demo_requests_table.php` (`status` `string(20)` por omisión `new` con `CHECK`; `note` `text` nullable; `handled_at` `timestampTz` nullable; `tenant_id` nullable con llave foránea `nullOnDelete`), `app/Modules/Demos/Enums/DemoStatus.php`, `app/Modules/Demos/Actions/UpdateDemoRequest.php`, `app/Modules/Demos/Contracts/DemoLeads.php`, `app/Modules/Demos/Data/DemoLead.php`, `app/Modules/Demos/Notifications/DemoRequested.php`
- Modify: `app/Modules/Demos/Models/DemoRequest.php:20-47`, `Actions/SubmitDemoRequest.php:14-34`, el controlador de la consola de demos (`update`), `routes/demos.php:13`, `resources/js/pages/demos/index.tsx`, `resources/js/components/demos/demo-request-detail.tsx:55`
- Modify (Tenancy): `Http/Controllers/TenantConsoleController.php` (prop `prefill` cuando llega `?demo={id}`), la acción de crear empresa (acepta `demo_request_id` y llama `DemoLeads::markConverted`), `resources/js/components/tenancy/create-tenant-dialog.tsx`
- Test: `tests/Feature/Demos/DemoRequestConsoleTest.php`, `DemoRequestTest.php`, `tests/Feature/Tenancy/CreateTenantTest.php`, `tests/Feature/MailInventoryTest.php`, `tests/Browser/DemoConsoleTest.php`

**Interfaces:**
- `enum DemoStatus: string` — `New` «Nueva», `Contacted` «Contactada», `Converted` «Convertida», `Discarded` «Descartada».
- `interface DemoLeads { public function find(int $id): ?DemoLead; public function markConverted(int $id, int $tenantId): void; }` · `DemoLead(int $id, string $company, string $contactName, string $email)`.
- `PUT admin/demos/{demoRequest}` con `status` (`Rule::enum`, sin `converted`: solo se llega creando la empresa) y `note` (`nullable`, `max:2000`).
- La lista gana pestañas con conteo por estado y el filtro `status`.

- [ ] **Step 1: Tests que fallan.**
  - Una solicitud nueva nace `new` y encola `DemoRequested` a cada superadmin (ninguno → no falla).
  - Cambiar a «Contactada» con nota guarda `handled_at` y registra `demo.updated` (log sin la nota).
  - Enviar `status=converted` por el `PUT` → 422.
  - «Crear empresa» lleva a la consola de empresas con `?demo={id}`; la página trae `prefill` con razón social y correo; crear la empresa deja la solicitud `converted` con su `tenant_id`. Segunda conversión de la misma solicitud → error «Esta solicitud ya se convirtió en {empresa}».
  - Pestañas: los conteos por estado cuadran; el filtro pagina.
- [ ] **Step 2–4:** Falla → migración, enum, acciones, contrato → verde.
- [ ] **Step 5:** Detalle: estado con `Select`, nota interna, botón «Crear empresa» (o «Ver empresa» si ya se convirtió). Estado vacío por pestaña. Claro, oscuro, 375 px.
- [ ] **Step 6: Commit** (tres): estado y nota · conversión en empresa · correo al llegar una solicitud.

### Task 4: El owner se entera de lo que le pasa a su empresa (decisión 16)

**Files:**
- Create: `app/Modules/Tenancy/Notifications/CompanyRestricted.php`, `CompanyReactivated.php`, `ToolsChanged.php`
- Modify: las acciones de Tenancy que restringen, suspenden y reactivan (`RestrictTenant` y la de reactivar), `RevokeModule`, `SyncCompanyModules`
- Test: `tests/Feature/Tenancy/TenantStatusTest.php`, `tests/Feature/Tenancy/CompanyModulesConsoleTest.php`, `tests/Feature/Billing/SubscriptionModulesTest.php`, `tests/Feature/MailInventoryTest.php`

- [ ] **Step 1: Tests que fallan** (con `Notification::fake()`):
  - poner en solo lectura o suspender → `CompanyRestricted` al owner con el motivo y el correo de soporte;
  - reactivar → `CompanyReactivated`;
  - quitar una herramienta, o un plan que quita varias → **un** `ToolsChanged` con las herramientas y la fecha en que dejan de estar; solo agregar → un correo con lo que se activó;
  - empresa sin owner → no se envía nada y el log `tenant.owner_not_notified` lo dice;
  - los correos tutean y no mencionan importes.
- [ ] **Step 2–4:** Falla → implementar (en cola, después del commit) → verde.
- [ ] **Step 5: Commit** (dos): estado de la empresa · cambio de herramientas.

### Task 5: «Plan y uso» para el owner (decisión 15)

**Files:**
- Create: `app/Modules/Tenancy/Http/Controllers/PlanController.php`, `app/Modules/Tenancy/Support/PlanPage.php`, ruta `tenant.plan` en `routes/company.php`, `resources/js/pages/tenancy/plan.tsx`, `resources/js/components/tenancy/plan-usage.tsx`, `plan-tools.tsx`
- Modify: el contrato de Billing que Tenancy ya usa para los límites (el que lee `WorkCenterController.php:60`): agregar el nombre del plan y su vigencia si no los expone; la navegación de la empresa (`resources/js/lib/app-navigation.ts`, bajo la configuración)
- Test: `tests/Feature/Tenancy/PlanPageTest.php` (nuevo), `tests/Unit/PaginatedListsTest.php` (`tools`: «catálogo fijo de herramientas»), `tests/Browser/TenantPagesTest.php`

**Interfaces:**
- Página `tenancy/plan` con `plan: {name, since, until} | null`, `usage: {centers: {used, limit}, people: {used, limit}}`, `tools: list<{label, active, endsOn}>`, `support: {email}`.

- [ ] **Step 1: Tests que fallan.** El owner ve su plan, sus límites con el uso real y sus herramientas (las que terminan, con su fecha). Empresa sin suscripción → `plan === null` y el estado vacío «Tu empresa aún no tiene un plan asignado». Las props no traen precios, cargos ni saldos. `hr` y `viewer` → 403. Otra empresa → 404.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** Página con dos medidores (centros, personas), lista de herramientas con estado y el contacto para ampliar. `visitPage('tenancy/plan', …)`; claro, oscuro, 375 px.
- [ ] **Step 6: Commit** `El owner ve su plan, sus límites y sus herramientas`.

### Task 6: Resumen de la plataforma (decisión 10; se puede cortar)

**Files:**
- Create: `database/migrations/2027_01_12_110000_add_last_login_at_to_users_table.php` (`timestampTz` nullable), `app/Listeners/RecordLastLogin.php` (escucha `Illuminate\Auth\Events\Login`; no registra mientras se impersona)
- Create: `app/Modules/Tenancy/Http/Controllers/PlatformOverviewController.php`, `app/Modules/Tenancy/Support/PlatformOverview.php`, `resources/js/pages/tenancy/overview.tsx`
- Modify: un contrato de Billing para el ingreso mensual contratado (`monthlyContractedCents(): int`, suma de las suscripciones vigentes), `Demos\Contracts\DemoLeads` (`countsByStatus(): array<string, int>`), `TenantConsoleController` y `tenant-row.tsx` (columna «Último acceso»: el más reciente de sus miembros, como día), `UserPages` (último acceso), `staff-navigation.ts` («Resumen»)
- Test: `tests/Feature/Tenancy/PlatformOverviewTest.php` (nuevo), `tests/Feature/Auth/` (test del listener), `tests/Feature/Tenancy/TenantConsoleTest.php`, `tests/Browser/TenantConsoleTest.php`

- [ ] **Step 1: Tests que fallan.** Iniciar sesión guarda `last_login_at`; impersonar no lo cambia. El resumen trae empresas por estado, ingreso mensual contratado, demos por estado y cuántas empresas no han entrado en 30 días; todo con un número fijo de consultas. La consola de empresas muestra el último acceso como día (sin hora). Solo superadmin.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** Página de tarjetas con cifra y enlace a su lista filtrada (sin gráficas: no hay series en el tiempo todavía). `visitPage('tenancy/overview', …)`; claro, oscuro, 375 px.
- [ ] **Step 6: Commit** (dos): último acceso · resumen de la plataforma.

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Recorrido como superadmin sin terminal: bitácora de una empresa, restablecer la contraseña de un usuario, convertir una demo, suspender y reactivar (revisar los correos en `http://localhost:3550`), y como owner su página de plan.
- [ ] Preguntar «¿Creo el PR?».
