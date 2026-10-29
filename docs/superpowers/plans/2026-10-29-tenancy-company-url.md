# La empresa en la URL, el bloqueo y la impersonación — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cada pantalla de una empresa vive bajo `/admin/{slug}`. Una empresa bloqueada no se puede usar. El superadmin
impersona al owner y deja rastro en logs y bitácora.

**Architecture:**
- Un middleware del módulo Tenancy (`ResolveTenant`) hace varias cosas sobre el grupo `admin/{tenant:slug}`:
  - exige membresía; si no la hay, responde 404 sin pasar por Gate (el `Gate::before` del superadmin lo dejaría pasar);
  - pone `tenant_id` en `Context`;
  - comparte a Inertia la empresa actual y las empresas del usuario.
- Otro middleware (`BlockSuspendedTenant`) aplica el bloqueo.
- `GET /admin` decide adónde ir.
- La impersonación es del kernel (`App\Http\Impersonation`):
  - inicia sesión como el owner y guarda el id del superadmin en la sesión;
  - `AssignRequestContext` agrega `impersonator_id` al `Context`, y `RecordAudit` lo escribe en la columna nueva
    `audit_entries.impersonator_id`.

**Tech Stack:** Laravel 13, Fortify, spatie/laravel-permission, Postgres, Pest 5 (Feature, Browser, `mutates()`),
Inertia v3 + React, Wayfinder, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-29-tenancy-company-url-design.md`

## Global Constraints

- **Reglas de `CLAUDE.md`:**
  - TDD estricto: ver el RED, y que falle por la razón correcta.
  - Larastan `max` y TS strictest, sin supresiones.
  - Clases `final` y `declare(strict_types=1)`.
  - Sin dependencias nuevas.
- Un commit por comportamiento, a nombre del usuario y sin atribución a Claude. Cada commit deja `composer test` en verde.
- Textos de UI en es-MX y tuteando. Código, rutas e identificadores en inglés.
- **Kernel y módulos:** el kernel (`App\Http`, `App\Models`, `App\Actions`, `App\Concerns`) no usa internos de
  Tenancy. Los internos de Tenancy solo se usan dentro del módulo y en `routes/`.
- Frontend:
  - el frame compartido va en `resources/js/components/*.tsx` y no importa de `components/tenancy/`;
  - límites del linter: 300 líneas por archivo, 100 por componente, complejidad 10;
  - imports con `@/`.
- Toda página Inertia nueva lleva un test `visitPage()` (`BrowserCoverageTest`).
- **Logs:**
  - el mensaje es un nombre de evento fijo; los datos van en el contexto, y solo ids, nunca nombres ni correos;
  - eventos de este PR: `impersonation.started`, `impersonation.stopped`, `impersonation.revoked`,
    `tenant.blocked_request_rejected`.
- Copy exacto:
  - modal: título **"El acceso a {trade_name} está en pausa"**; cuerpo **"Puede deberse a un pago pendiente o a que
    terminó tu periodo de prueba o de servicio. Para reactivarlo, contacta al administrador de Humetría."**;
  - sin empresas: **"Aún no tienes acceso a ninguna empresa. Contacta al administrador de Humetría."**;
  - etiqueta de prueba: **"Prueba · quedan N días"**, o **"Prueba · último día"** cuando N = 1;
  - franja: **"Estás viendo Humetría como {nombre} · Salir"**;
  - consola: **"Entrar como owner"**.
- **Cambios de tests existentes:** solo los que exige la decisión 1 del usuario: `/admin` ya no pinta el Panel. Se
  ajustan en la Task 3, y en el commit se dice que el comportamiento lo cambió el usuario.

## Review Focus

1. **Orden de rutas:** `admin/{tenant}` captura cualquier segmento. Si queda registrada antes que `admin/settings`,
   `admin/companies` o `admin/demo-requests`, esas páginas dan 404. El test va en la Task 2: esas rutas siguen
   respondiendo con una empresa que tenga un slug cualquiera.
2. **Pestaña vieja en una empresa recién bloqueada**, que hace `POST`: debe responder 423 sin tocar nada. Va en la
   Task 6, con un `POST` a una ruta de prueba registrada dentro del grupo de la empresa.
3. **Superadmin que además es miembro de una empresa** y entra a `/admin`: va a la consola (decisión de Claude 8). Va
   en la Task 3.
4. **Doble clic en "Salir"**, o "Salir" desde otra pestaña cuando la impersonación ya terminó: el segundo `POST` da
   404 y no cambia la sesión. Va en la Task 7.
5. **Owner con dos empresas, una bloqueada:** el selector y la lista muestran las dos, y la bloqueada abre el modal con
   "Cambiar de empresa". Va en las Tasks 3 y 6.

---

### Task 1: `impersonator_id` en el Context y en la bitácora

**Files:**
- Create: `app/Http/Impersonation.php`
- Create: `database/migrations/2026_10_29_000000_add_impersonator_id_to_audit_entries_table.php`
- Modify: `app/Http/Middleware/AssignRequestContext.php`, `app/Actions/RecordAudit.php`, `app/Models/AuditEntry.php`
- Test: `tests/Feature/AuditTrailTest.php`, `tests/Feature/Logging/` (el archivo que ya prueba el `Context`)

**Interfaces:**
- Produces: `App\Http\Impersonation` (final), con:
  - `public const string SESSION_KEY = 'impersonator_id';`
  - `__construct(Illuminate\Contracts\Session\Session $session)`
  - `impersonatorId(): ?int`
  - `isActive(): bool`
- La Task 7 le agrega `start()` y `stop()`.

- [ ] **Step 1: Tests que fallan**
  - `it('records the impersonator on every audit entry while impersonating')`: con
    `session([Impersonation::SESSION_KEY => $superadmin->id])` y `actingAs($owner)`, se hace una petición a una ruta
    que escribe bitácora. Se espera `AuditEntry` con `actor_id === $owner->id` e `impersonator_id === $superadmin->id`.
    Si no hay ruta con bitácora a la mano, se registra una ruta de prueba en el test que llame a `RecordAudit`.
  - `it('leaves impersonator_id null outside an impersonation')`.
  - `it('puts impersonator_id on every log line while impersonating')`: con `Log::spy()` o el patrón del test de
    `Context` existente, `Context::get('impersonator_id') === $superadmin->id`.
- [ ] **Step 2: Correr y ver RED**: `php artisan test --compact tests/Feature/AuditTrailTest.php`. Falla porque la
  columna `impersonator_id` no existe.
- [ ] **Step 3: Implementar**
  - Migración: `unsignedBigInteger('impersonator_id')->nullable()->index()`, **sin FK**, con un comentario igual al de
    `actor_id`. El `ALTER TABLE` no lo bloquea el trigger, que solo frena UPDATE, DELETE y TRUNCATE de filas.
  - `RecordAudit` agrega `'impersonator_id' => Context::get('impersonator_id')`.
  - `AuditEntry` suma la propiedad y el fillable.
  - `AssignRequestContext` agrega `impersonator_id` cuando hay `Impersonation::isActive()`.
- [ ] **Step 4: GREEN** con el mismo comando, más `vendor/bin/phpstan analyse`.
- [ ] **Step 5: Commit** `Record who impersonates on every log line and audit entry`

### Task 2: El Panel bajo `admin/{tenant:slug}`, solo para miembros

**Files:**
- Create: `app/Modules/Tenancy/Http/Middleware/ResolveTenant.php`
- Create: `app/Modules/Tenancy/Http/Controllers/TenantDashboardController.php` (invocable)
- Create: `routes/company.php`, incluido **al final** de `routes/web.php`, después del grupo `admin` existente
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, que comparte `'tenant' => null` y `'tenants' => []` por omisión
- Modify: `resources/js/types/global.d.ts`, `resources/js/types/tenancy.ts`
- Test: `tests/Feature/Tenancy/TenantRoutingTest.php`, y helper `ownerOf(Tenant $tenant): User` en `tests/Pest.php`

**Interfaces:**
- Produces:
  - Ruta `tenant.dashboard`: `GET admin/{tenant:slug}`, con middleware `auth`, `verified` y `ResolveTenant`. Renderiza
    `dashboard`, el componente actual sin cambios.
  - Prop compartida `tenant: CurrentTenant | null`, con
    `CurrentTenant = { slug: string; trade_name: string; status: TenantRow['status']; blocked: boolean; trial_days_left: number | null }`.
    `trial_days_left` es `null` hasta la Task 4.
  - Prop compartida `tenants: { slug: string; trade_name: string }[]`, las empresas del usuario ordenadas por
    `trade_name`.
  - `ResolveTenant` es el único lugar que hace `Context::add('tenant_id', $tenant->id)`.

- [ ] **Step 1: Tests que fallan**
  - Owner → `assertInertia(component('dashboard')->where('tenant.slug', 'acme')->where('tenant.trade_name', …))`.
  - Miembro de otra empresa → 404. Slug inexistente → 404. Superadmin sin membresía → 404. Sin sesión → login.
  - Dentro de la empresa, una entrada de `RecordAudit` lleva su `tenant_id`. Se prueba con una ruta de prueba
    registrada en el test con el middleware `ResolveTenant`.
  - `tenants` trae las dos empresas de un owner con dos empresas, ordenadas, y nunca una empresa ajena.
  - *Review Focus 1:* con una empresa de slug `acme`, `GET /admin/settings/appearance`, `GET /admin/companies` (como
    superadmin) y `GET /admin/login` (sin sesión) siguen respondiendo 200.
- [ ] **Step 2: RED**: `php artisan test --compact tests/Feature/Tenancy/TenantRoutingTest.php`. Falla porque la ruta
  `tenant.dashboard` no existe.
- [ ] **Step 3: Implementar.** `ResolveTenant::handle()` hace, en orden:
  1. toma el `Tenant` ligado por la ruta;
  2. revisa `TenantMembership::query()->where(tenant_id, user_id)->exists()`; si no existe, `abort(404)`;
  3. `Context::add`;
  4. `Inertia::share('tenant', …)` y `Inertia::share('tenants', …)`.

  El controlador renderiza `dashboard`.
- [ ] **Step 4: GREEN**, más phpstan y `npm run types:check`.
- [ ] **Step 5: Commit** `Open each company's panel at /admin/{slug}, only to its members`

### Task 3: `/admin` decide adónde ir

**Files:**
- Create: `app/Modules/Tenancy/Http/Controllers/HomeController.php` (invocable), que reemplaza el closure de `dashboard`
  en `routes/web.php`. La ruta conserva el nombre `dashboard` y la ruta `/admin`, así que Fortify y aceptar invitación
  no cambian.
- Create: `resources/js/pages/tenancy/companies.tsx`
- Modify: `tests/Feature/AdminRoutingTest.php` y `tests/Feature/DashboardTest.php`. *Lo pide la decisión 1 del usuario.*
- Test: `tests/Feature/Tenancy/HomeRedirectTest.php`, `tests/Browser/TenantPagesTest.php`

**Interfaces:**
- Consumes: `tenant.dashboard` (Task 2).
- Produces: página `tenancy/companies` con props
  `companies: { slug: string; trade_name: string; status: TenantRow['status']; status_label: string; blocked: boolean }[]`.
  Con la lista vacía muestra el copy "sin empresas" y "Cerrar sesión".

- [ ] **Step 1: Tests que fallan**
  - Superadmin → redirect a `companies.index`. También el superadmin que además es miembro (*Review Focus 3*).
  - 0 empresas → `component('tenancy/companies')->has('companies', 0)`.
  - 1 empresa → redirect a `tenant.dashboard`.
  - 2 empresas, una bloqueada → `has('companies', 2)` y la bloqueada con `blocked: true` (*Review Focus 5*).
  - Las props de `tenancy/companies` no traen RFC, razón social ni ids.
  - `AdminRoutingTest`: el caso "verified users see the dashboard at /admin" pasa a "a user without companies sees
    tenancy/companies at /admin". `DashboardTest` visita `tenant.dashboard` con un owner.
- [ ] **Step 2: RED** con los dos archivos.
- [ ] **Step 3: Implementar** el controlador y la página. La página usa el layout de la app, un `Card` por empresa con
  enlace a `tenant.dashboard` y el badge de estado de `tenantStatusVariant`. Con la lista vacía usa `EmptyState`.
- [ ] **Step 4: GREEN**. Browser: `visitPage('tenancy/companies', …)` con 0 y 2 empresas, en claro, oscuro y móvil.
  Hay que correr `npm run build` antes.
- [ ] **Step 5: Commit** `Send each person from /admin to their company, their list of companies, or the console`

### Task 4: Días de prueba

**Files:**
- Modify: `app/Modules/Tenancy/Models/Tenant.php`, que suma `trialDaysLeft(): ?int`
- Modify: `ResolveTenant`, para llenar `trial_days_left`
- Modify: `resources/js/lib/tenant-status.ts`, que suma `trialLabel(daysLeft: number): string`
- Test: `tests/Feature/Tenancy/TrialDaysLeftTest.php` con `mutates(Tenant::class)`, y `tests/js/lib/tenant-status.test.ts`

**Interfaces:**
- Produces:
  - `Tenant::trialDaysLeft(): ?int`: `null` fuera de `TenantStatus::Trial`; si no, `ceil(segundos restantes / 86400)`.
  - `trialLabel(1) === 'Prueba · último día'` y `trialLabel(9) === 'Prueba · quedan 9 días'`.

- [ ] **Step 1: Tests que fallan.** Con `travelTo`, a partir de `trial_started_at`:
  - recién iniciada → 14;
  - faltan 13 días y 1 s → 14;
  - faltan exactamente 13 días → 13;
  - falta exactamente 1 día → 1;
  - falta 1 s → 1;
  - en el instante de `trialEndsAt()` → `null` (ya es TrialEnded);
  - activa, sin iniciar y desactivada → `null`.

  En Vitest, `trialLabel` con 1, 2 y 14.
- [ ] **Step 2: RED** (`php artisan test --compact --filter=TrialDaysLeft`; `npm run test:js`).
- [ ] **Step 3: Implementar** las dos funciones y pasar el valor en la prop `tenant`.
- [ ] **Step 4: GREEN**, más `composer test:mutate` sobre `Tenant`, con score ≥ 85%.
- [ ] **Step 5: Commit** `Tell a company in trial how many days it has left`

### Task 5: Selector de empresa y navegación con el slug

**Files:**
- Create: `resources/js/components/tenant-switcher.tsx`, en el frame: nombre comercial más la etiqueta de prueba.
  Con 2+ empresas es un `DropdownMenu` con enlaces a `tenant.dashboard` de cada una; con una sola es texto.
- Modify: `resources/js/components/app-sidebar.tsx`, que pone el selector bajo el logo cuando `tenant !== null`. El
  enlace del logo va al Panel de la empresa actual, o a `/admin` si no hay empresa.
- Modify: `resources/js/lib/app-navigation.ts`, donde `mainNavItems` pasa a ser
  `mainNavItems(tenantSlug: string | null): NavItem[]`; "Panel" lleva a `tenant.dashboard` o a `dashboard`. Se
  actualizan sus usos en `app-header/*` y `lib/palette-commands.ts`.
- Test: `tests/js/lib/` (los tests de `app-navigation` y de `palette-commands`, si existen), y en
  `tests/Browser/TenantPagesTest.php` el flujo de cambiar de empresa.

- [ ] **Step 1: Tests que fallan.**
  - Vitest: `mainNavItems('acme')[0].href` apunta a `/admin/acme`, y `mainNavItems(null)[0].href` a `/admin`.
  - Browser: un owner con Acme y Beta entra a Acme, abre el selector, elige Beta y llega a `/admin/beta` con
    `visitPage('dashboard', …)`. Con una sola empresa, el selector no tiene menú.
- [ ] **Step 2: RED.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: GREEN**: `npm run test:js`, `npm run types:check`, `vp lint` y el test de navegador.
- [ ] **Step 5: Commit** `Switch companies from the sidebar`

### Task 6: Bloqueo

**Files:**
- Create: `app/Modules/Tenancy/Http/Middleware/BlockSuspendedTenant.php`, que va después de `ResolveTenant` en
  `routes/company.php`
- Create: `resources/js/pages/tenancy/blocked.tsx`
- Create: `resources/js/components/tenant-blocked-dialog.tsx` (frame), con
  props `{ tradeName: string; canSwitch: boolean; dismissible: boolean }`
- Test: `tests/Feature/Tenancy/TenantBlockTest.php`, `tests/Browser/TenantPagesTest.php`

**Interfaces:**
- Consumes: `Impersonation::isActive()` (Task 1). Con la impersonación activa el middleware no hace nada, y la Task 9
  muestra el modal.
- Produces: página `tenancy/blocked`, sin props propias: solo las compartidas `tenant` y `tenants`.

- [ ] **Step 1: Tests que fallan.** Con `->with()`, para TrialEnded, Expired y Deactivated:
  - `GET tenant.dashboard` → `component('tenancy/blocked')` sin ninguna prop de `dashboard`;
  - `POST` a una ruta de prueba dentro del grupo de la empresa → **423**, sin efecto, y `Log` con
    `tenant.blocked_request_rejected` y `['tenant_id' => …]` (*Review Focus 2*);
  - una empresa en prueba o activa → pasa normal.
- [ ] **Step 2: RED.**
- [ ] **Step 3: Implementar.**
  - Middleware: si `tenant->status()->isBlocked()` y no hay impersonación: con `GET` o `HEAD`, `Inertia::render('tenancy/blocked')`;
    con otro método, log y `abort(423)`.
  - El modal sigue la guía `Dialog` y no se cierra con Esc ni con clic afuera cuando `dismissible` es false.
  - Botones: "Cerrar sesión" (`logout`) y, si `canSwitch` (`tenants.length > 1`), "Cambiar de empresa", que lleva a
    `dashboard`.
- [ ] **Step 4: GREEN**, más `visitPage('tenancy/blocked', …)` con una y con dos empresas, en claro, oscuro y móvil.
- [ ] **Step 5: Commit** `Block a suspended company: a modal over every page and 423 for any change`

### Task 7: Entrar como owner y salir

**Files:**
- Modify: `app/Http/Impersonation.php`, que suma:
  - `start(User $impersonator, User $target): void`
  - `stop(): ?User`: devuelve al superadmin, o `null` si ya no es superadmin y se cerró la sesión
- Create: `app/Modules/Tenancy/Http/Controllers/ImpersonateOwnerController.php` (invocable):
  `POST admin/companies/{tenant}/impersonate`, `companies.impersonate`, en `routes/tenancy.php`
- Create: `app/Http/Controllers/StopImpersonationController.php` (invocable):
  `POST admin/impersonation/stop`, `impersonation.stop`, con `auth`, en el grupo `admin` de `routes/web.php`
- Create: `app/Http/Middleware/EndRevokedImpersonation.php`, agregado al grupo `web` **antes** de `AssignRequestContext`
- Create: `resources/js/components/impersonation-banner.tsx` (frame), puesto arriba en `app-sidebar-layout.tsx` y
  `app-header-layout.tsx`
- Modify: `HandleInertiaRequests`, que comparte `impersonator: string | null` (el nombre del superadmin)
- Modify: `resources/js/components/tenancy/tenant-row-actions.tsx`, con el item "Entrar como owner" cuando
  `tenant.owner !== null`. Hace POST con `router.post` o `Link as="button"`.
- Modify: `TenantRules::RESERVED_SLUGS`, que suma `'impersonation'`
- Test: `tests/Feature/ImpersonationTest.php`, `tests/Feature/Tenancy/ReservedSlugsTest.php`,
  `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- `start()`:
  1. `RecordAudit('impersonation.started', $target, ['tenant_id' => …])` como el superadmin; el controlador pasa el
     `tenant_id` del contexto con `Context::scope`;
  2. `Log::info('impersonation.started', ids)`;
  3. `Auth::login($target)`, `session()->regenerate()` y `put(SESSION_KEY, $impersonator->id)`.
- `stop()`:
  1. registra `impersonation.stopped` mientras la sesión sigue siendo la del owner, así que lleva actor = owner e
     `impersonator_id`;
  2. `forget(SESSION_KEY)`, `Auth::login($impersonator)` y `regenerate()`.

- [ ] **Step 1: Tests que fallan**
  - El superadmin entra: la sesión es del owner, `session(SESSION_KEY) === superadmin->id`, redirige a `dashboard`, y
    la bitácora tiene `impersonation.started` con actor = superadmin y subject = owner.
  - Una acción durante la impersonación queda con actor = owner e `impersonator_id` = superadmin.
  - Salir: la sesión vuelve al superadmin, redirige a `companies.index` y se registra `impersonation.stopped`.
  - Segundo "Salir" → 404, sin cambios (*Review Focus 4*).
  - Prohibido, 404 en cada caso: un no superadmin; una empresa sin owner; un owner que es superadmin; impersonar
    estando impersonando (el owner no pasa el Gate).
  - Revocado: a media impersonación se le quita el rol al superadmin (o se borra su cuenta). La siguiente petición
    cierra la sesión, redirige a login y escribe `impersonation.revoked`.
  - Ninguna línea de log ni fila de bitácora de estos eventos contiene nombre ni correo.
  - `ReservedSlugsTest`: `impersonation` está reservado.
  - Browser: "Entrar como owner" desde la consola → franja visible → "Salir" → consola.
- [ ] **Step 2: RED.**
- [ ] **Step 3: Implementar.** `EndRevokedImpersonation` hace esto cuando `isActive()` y el impersonador no existe o
  `! hasRole(User::SUPERADMIN)`:
  1. log `impersonation.revoked`;
  2. `Auth::logout()`, `invalidate()` y `regenerateToken()`;
  3. redirige a `login`.
- [ ] **Step 4: GREEN**, más phpstan, `types:check` y el test de navegador.
- [ ] **Step 5: Commit** `Let the superadmin work as a company's owner and come back, leaving a trail`

### Task 8: Proteger la cuenta del owner mientras se impersona

**Files:**
- Create: `app/Http/Middleware/ForbidWhileImpersonating.php`, que responde 404 si `Impersonation::isActive()`
- Modify: `routes/settings.php`: el middleware va en `profile.edit`, `profile.update`, `profile.destroy`,
  `security.edit`, `user-password.update`, y en las rutas de 2FA y passkeys de Fortify que estén bajo
  `admin/user/*` (se agrupan o se protegen por nombre en el middleware)
- Modify: `resources/js/layouts/settings/layout.tsx`, que oculta Perfil y Seguridad si `impersonator !== null`;
  `resources/js/components/user-menu-content.tsx`, donde "Configuración" lleva a `appearance.edit` al impersonar
- Test: `tests/Feature/Settings/ImpersonationSettingsTest.php`

- [ ] **Step 1: Tests que fallan.** Al impersonar, cada ruta de la lista responde 404 y `appearance.edit` responde 200.
  Sin impersonar, las mismas responden como hoy.
- [ ] **Step 2: RED.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: GREEN.**
- [ ] **Step 5: Commit** `Keep the owner's own account out of reach while impersonating`

### Task 9: El superadmin impersonando cierra el modal de bloqueo

**Files:**
- Modify: `resources/js/layouts/app/app-sidebar-layout.tsx` y `app-header-layout.tsx`: cuando
  `tenant?.blocked && impersonator !== null`, muestran `TenantBlockedDialog` con `dismissible` y abierto al cargar
- Test: `tests/Feature/Tenancy/TenantBlockTest.php` y `tests/Browser/TenantPagesTest.php`

- [ ] **Step 1: Tests que fallan.**
  - Feature: impersonando en una empresa Vencida, `GET tenant.dashboard` → `component('dashboard')` con
    `tenant.blocked === true` e `impersonator` presente, y el `POST` de prueba no da 423.
  - Browser: se ve el modal, se cierra con su botón y el Panel queda usable.
- [ ] **Step 2: RED.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: GREEN.**
- [ ] **Step 5: Commit** `Let an impersonating superadmin close the block modal`

### Task 10: Documentación y verificación final

**Files:**
- Modify: `CLAUDE.md` → "Logging y auditoría". Cambia "Al crear el módulo Tenancy, agregar ahí `tenant_id` con
  `Context::add`" por "`tenant_id` lo agrega `ResolveTenant` (Tenancy) en las rutas de una empresa; `impersonator_id` lo
  agrega `AssignRequestContext` mientras se impersona."

- [ ] **Step 1:** Editar `CLAUDE.md`.
- [ ] **Step 2: Verificación completa:**
  - `composer test`
  - `composer test:mutate`
  - `npm run types:check`
  - `npm run test:js`
  - `npm run build && composer test:browser`

  Todo en verde.
- [ ] **Step 3: Commit** `Say where tenant_id and impersonator_id enter the log context`
