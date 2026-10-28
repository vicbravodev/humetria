# Consola "Empresas" (Tenancy) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El superadmin da de alta, lista, edita, activa, desactiva y reactiva empresas desde `/admin/companies`.

**Architecture:** Módulo nuevo `app/Modules/Tenancy` con el modelo `Tenant` (UUID, sin `tenant_id` ni global scope). El
estado no se guarda: `Tenant::status()` lo calcula de `trial_started_at`, `active_until` y `deactivated_at` contra
`now()`. Una sola página Inertia (`tenancy/index`) con la tabla y los Dialogs, siguiendo el patrón de la consola de demos
(`app/Modules/Demos`, `resources/js/pages/demos/index.tsx`).

**Tech Stack:** Laravel 13, Postgres, Pest 5 (Feature, Browser y `mutates()`), Inertia v3 + React, Wayfinder, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-27-tenancy-create-company-design.md`

## Global Constraints

- Reglas de `CLAUDE.md`: TDD estricto (ver el RED antes del código), Larastan `max` y TS strictest sin supresiones,
  clases `final`, `declare(strict_types=1)`, sin dependencias nuevas.
- Solo `Contracts`, `Events` y `Data` son públicos; `Models`, `Actions` y `Enums` de Tenancy no se importan fuera del módulo
  ni de `Database\Factories\Tenancy`. Las acciones no tocan HTTP.
- Identificadores en inglés; textos de UI en español (es-MX), tuteando. Ningún texto visible en inglés (`visitPage` lo revisa).
- Fechas en UTC (`timestampTz`); se muestran en `config('app.display_timezone')` con el formato de la consola de demos
  (`27 oct 2026`, sin el punto que Carbon pone a los meses).
- La prueba dura **14 días** (`Tenant::TRIAL_DAYS`); no es configuración.
- Logs: nombre de evento fijo, datos en el contexto, **sin valores de campos**: solo nombres de campos y `active_until`.
- Autorización: Gate `manage-tenants` con `denyAsNotFound()`; el superadmin pasa por el `Gate::before` existente.
- Un commit por tarea, con `composer test` en verde al cerrar cada una (más `npm run test:js` y `npm run types:check` si
  tocó frontend). Mensajes de commit en inglés, en imperativo, como los de `git log`.

## Review Focus

1. **Activar cerca de la medianoche:** a las 21:00 de Ciudad de México ya es el día siguiente en UTC. "Hoy" para la regla
   "posterior a hoy" es el de la Ciudad de México → test en la Tarea 5.
2. **El instante exacto de los bordes:** a `trial_started_at + 14 días` exactos la prueba ya terminó; en `active_until`
   exacto ya venció (`now < fin`) → tests en la Tarea 1.
3. **RFC o slug con otra capitalización o espacios** chocan con uno existente: se normalizan antes de validar `unique`
   → tests en las Tareas 3 y 4.
4. **Editar sin cambiar el RFC ni el slug** no debe chocar consigo mismo → test en la Tarea 4.
5. **Slug con acentos o ñ** ("Ñandú Café") propuesto igual en el navegador y aceptado por el servidor → tests en la Tarea 3.

---

### Task 1: Modelo `Tenant`, migración y cálculo del estado

**Files:**
- Create: `database/migrations/2026_10_27_200000_create_tenants_table.php`
- Create: `app/Modules/Tenancy/Models/Tenant.php`
- Create: `app/Modules/Tenancy/Enums/TenantStatus.php`
- Create: `database/factories/Tenancy/TenantFactory.php`
- Test: `tests/Feature/Tenancy/TenantStatusTest.php`, `tests/Feature/Tenancy/TenantStorageTest.php`

**Interfaces:**
- Produces:
  - `Tenant` (`HasVersion4Uuids`, `#[UseFactory(TenantFactory::class)]`, `#[Fillable(['legal_name','trade_name','rfc','slug','trial_started_at','active_until','deactivated_at'])]`),
    casts `immutable_datetime` en las tres fechas; `@property` para cada columna.
  - `public const int TRIAL_DAYS = 14;`
  - `Tenant::status(): TenantStatus` — prioridad del spec: `deactivated_at` → Deactivated; `active_until` → Active si
    `now() < active_until`, si no Expired; sin `trial_started_at` → NotStarted; `now() < trialEndsAt()` → Trial; si no TrialEnded.
  - `Tenant::trialEndsAt(): ?CarbonImmutable` (`trial_started_at + TRIAL_DAYS`, `null` si no empezó).
  - `enum TenantStatus: string` con `NotStarted='not_started'`, `Trial='trial'`, `Active='active'`,
    `TrialEnded='trial_ended'`, `Expired='expired'`, `Deactivated='deactivated'`; `label(): string`
    (`Sin iniciar`, `En prueba`, `Activa`, `Prueba terminada`, `Vencida`, `Desactivada`); `isBlocked(): bool`
    (true en TrialEnded, Expired, Deactivated).
  - Factory: definición con `legal_name` = `fake()->company().' S.A. de C.V.'`, `trade_name` = `fake()->company()`, `rfc` válido
    único (3 letras + `fake()->date('ymd')` + 3 alfanuméricos, en mayúsculas), `slug` = `Str::slug(trade_name).'-'.fake()->unique()->numberBetween(1, 99999)`.
    Estados: `inTrial(CarbonInterface $startedAt)`, `activeUntil(CarbonInterface $until)`, `deactivated()`.

- [ ] **Step 1: Tests de estado (RED).** `TenantStatusTest.php` con `mutates(Tenant::class, TenantStatus::class)` y
  `$this->travelTo(CarbonImmutable::parse('2026-10-27 12:00:00', 'UTC'))`:
  - `it('is not started until the owner signs in')` → `Tenant::factory()->make()->status()` es `NotStarted`.
  - `it('is in trial until the instant 14 days after the owner signs in')`: con `inTrial(now()->subDays(14)->addSecond())` → `Trial`;
    `inTrial(now()->subDays(14))` → `TrialEnded`; `inTrial(now()->subDays(14)->subSecond())` → `TrialEnded`.
  - `it('is active before active_until and expired from that instant')`: `activeUntil(now()->addSecond())` → `Active`;
    `activeUntil(now())` → `Expired`.
  - `it('is active over a finished trial')`: `inTrial(now()->subDays(30))` + `activeUntil(now()->addMonth())` → `Active`.
  - `it('is deactivated even with a future active_until')`: `activeUntil(now()->addMonth())->deactivated()` → `Deactivated`.
  - `it('ends the trial 14 days after it starts')`: `trialEndsAt()` = start + 14 días; `null` sin start.
  - `it('labels each status and says which ones block')`: dataset de los 6 casos con su `label()` e `isBlocked()`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Tenancy/TenantStatusTest.php` → FAIL (`Class "App\Modules\Tenancy\Models\Tenant" not found`).
- [ ] **Step 3: Tests de integridad (RED).** `TenantStorageTest.php`, cada uno con `->throws(QueryException::class, '<constraint>')`:
  RFC duplicado (`tenants_rfc_unique`), slug duplicado (`tenants_slug_unique`), RFC `abc010101xy1` en minúsculas
  (`tenants_rfc_format`), slug `Grupo_Acme` (`tenants_slug_format`), `legal_name` `'  '` (`tenants_legal_name_filled`),
  `trade_name` `''` (`tenants_trade_name_filled`). Más un `it('stores the id as a random UUID v4')`.
- [ ] **Step 4: Migración.** `uuid('id')->primary()`, `string('legal_name', 200)`, `string('trade_name', 120)`,
  `string('rfc', 13)->unique()`, `string('slug', 50)->unique()`, `timestampTz` nullable para `trial_started_at`,
  `active_until` y `deactivated_at`, `timestampsTz()`. Checks con `DB::statement` como en `create_demo_requests_table`:
  `tenants_rfc_format CHECK (rfc ~ '^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$')`,
  `tenants_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$' AND char_length(slug) BETWEEN 3 AND 50)`,
  `tenants_legal_name_filled CHECK (btrim(legal_name) <> '')` y `tenants_trade_name_filled CHECK (btrim(trade_name) <> '')`.
  Nombra los índices únicos `tenants_rfc_unique` y `tenants_slug_unique`. `down()` borra la tabla.
- [ ] **Step 5:** modelo, enum y factory según **Interfaces**.
- [ ] **Step 6:** `php artisan test --compact tests/Feature/Tenancy` → PASS; `composer test` → verde (ArchitectureTest
  incluido: el módulo nuevo entra solo en sus reglas).
- [ ] **Step 7: Commit** `Add the Tenant model, whose status follows its trial and paid dates`.

### Task 2: Consola "Empresas": listar

**Files:**
- Create: `app/Modules/Tenancy/Actions/ListTenants.php`, `app/Modules/Tenancy/Http/Controllers/TenantConsoleController.php`,
  `app/Modules/Tenancy/Providers/TenancyServiceProvider.php`, `routes/tenancy.php`
- Modify: `bootstrap/providers.php`, `routes/web.php` (`require __DIR__.'/tenancy.php';` junto a `demos.php`)
- Create: `resources/js/pages/tenancy/index.tsx`, `resources/js/components/tenancy/tenants-table.tsx`,
  `resources/js/components/tenancy/tenant-row.tsx`, `resources/js/components/tenancy/tenants-empty.tsx`,
  `resources/js/types/tenancy.ts`, `resources/js/lib/tenant-status.ts`
- Create: `resources/js/components/console-pagination.tsx` (sale de `components/demos/demo-requests-pagination.tsx`, que se borra)
- Modify: `resources/js/types/index.ts`, `resources/js/components/app-sidebar.tsx`, `resources/js/pages/demos/index.tsx`
- Modify: `tests/Pest.php` (recibe `superadmin(): User`, que hoy vive en `tests/Feature/Demos/DemoRequestConsoleTest.php`,
  y `signInAsSuperadmin()` de `tests/Browser/DemoConsoleTest.php`; los dos se borran de su archivo original)
- Test: `tests/Feature/Tenancy/TenantConsoleTest.php`, `tests/Browser/TenantConsoleTest.php`, `tests/js/lib/tenant-status.test.ts`

**Interfaces:**
- Consumes: `Tenant`, `Tenant::status()`, `Tenant::trialEndsAt()`, `TenantStatus::label()`, factory states (Tarea 1).
- Produces:
  - Ruta `GET /admin/companies` → `companies.index` (`auth`, `verified`), `TenantConsoleController::index(ListTenants): Response`.
    Las rutas de las tareas siguientes se agregan a este mismo archivo y controlador.
  - Gate `manage-tenants` en `TenancyServiceProvider::boot()`.
  - `ListTenants::handle(): LengthAwarePaginator<int, Tenant>` — más recientes primero, 25 por página.
  - Prop `tenants` paginado; cada fila: `id`, `legal_name`, `trade_name`, `rfc`, `slug`, `status` (valor del enum),
    `status_label`, `status_date` (`string|null`), `active_until` (`Y-m-d` en hora de CDMX o `null`; la usa el Dialog de
    activar), `blocked` (bool).
  - `status_date` por estado: NotStarted `null`; Trial `Prueba hasta {trialEndsAt}`; TrialEnded `Prueba terminó el {trialEndsAt}`;
    Active `Activa hasta {active_until}`; Expired `Venció el {active_until}`; Deactivated `Desactivada el {deactivated_at}`.
    Fechas con el formato `D MMM YYYY` de la consola de demos, en hora de CDMX. Para `active_until`, que se guarda al final
    del día, se muestra ese día.
  - TS: `type TenantRow` en `types/tenancy.ts` con esos campos; `status` es la unión literal de los 6 valores.
  - `lib/tenant-status.ts`: `tenantStatusVariant(status: TenantRow['status']): 'default' | 'secondary' | 'outline' | 'destructive'`
    (`active` → `default`, `trial` → `secondary`, `not_started` → `outline`, los tres bloqueados → `destructive`).
  - `ConsolePagination` con props `{ page: Paginated<unknown>; label: string }` (el `aria-label`); textos "Página X de Y",
    "Ver más recientes", "Ver más antiguas". La consola de demos lo usa con `label="Páginas de solicitudes"`.

- [ ] **Step 1: Mover los helpers** `superadmin()` y `signInAsSuperadmin()` a `tests/Pest.php`; `composer test` sigue verde
  (es un refactor de tests sin cambio de comportamiento: ninguna aserción cambia).
- [ ] **Step 2: Feature tests (RED)**, espejo de `DemoRequestConsoleTest`, con `mutates(TenantConsoleController::class, ListTenants::class)`:
  - `it('lives at /admin/companies')`; `it('sends guests to the login')`;
    `it('answers 404 to anyone who is not a superadmin')`.
  - `it('lists each company with its status for the superadmin')`: con `travelTo('2026-10-27 18:00 UTC')` y una empresa
    `inTrial(CarbonImmutable::parse('2026-10-20 18:00', 'UTC'))` → `where('tenants.data', [[... 'status' => 'trial',
    'status_label' => 'En prueba', 'status_date' => 'Prueba hasta 3 nov 2026', 'active_until' => null, 'blocked' => false]])`.
  - `it('describes the date of every status')`: dataset de los 6 estados con su `status_date`, incluido
    `activeUntil(CarbonImmutable::parse('2026-12-31', 'America/Mexico_City')->endOfDay())` → `Activa hasta 31 dic 2026` y `active_until` `2026-12-31`.
  - `it('shows the newest companies first')`, `it('pages the list 25 at a time')`, `it('renders the empty console')`.
  - `it('reads the page in four queries')` con `expectsDatabaseQueryCount(4)`, como la consola de demos.
- [ ] **Step 3:** correr `tests/Feature/Tenancy/TenantConsoleTest.php` → FAIL (`Route [companies.index] not defined`).
- [ ] **Step 4:** backend según **Interfaces**; `php artisan wayfinder:generate` si el plugin de Vite no corre.
- [ ] **Step 5:** `tests/js/lib/tenant-status.test.ts` (RED → GREEN) con los 6 valores; luego página, componentes,
  `ConsolePagination` y el enlace del sidebar: `{ title: 'Empresas', href: companies(), icon: Building2 }` en
  `superadminNavItems`, antes de "Solicitudes de demo". Encabezado `PageHeader caption="Plataforma" title="Empresas"`,
  conteo "1 empresa" / "N empresas", vacío "Aún no hay empresas". Columnas: Empresa (nombre comercial y debajo la razón
  social), RFC, Estado (`Badge` con `status_label` y debajo `status_date`).
- [ ] **Step 6: Browser test** `tests/Browser/TenantConsoleTest.php`: `visitPage('tenancy/index', '/admin/companies', $dark, $mobile)`
  con el dataset `$looks` de `DemoConsoleTest` y una empresa fija (`trade_name` "Grupo Ejemplo", `legal_name`
  "Grupo Ejemplo S.A. de C.V.", `rfc` "GEJ0101011A1"), que afirma que se ven el nombre, el RFC y "Sin iniciar"; el vacío; y que
  el sidebar lleva a `/admin/companies` solo para el superadmin. Usa textos fijos, no Faker, en lo que se ve.
- [ ] **Step 7:** `composer test`, `npm run test:js`, `npm run types:check` → verde.
- [ ] **Step 8: Commit** `List companies and their status in a superadmin console`.

### Task 3: Dar de alta una empresa

**Files:**
- Create: `app/Modules/Tenancy/Data/TenantData.php`, `app/Modules/Tenancy/Actions/CreateTenant.php`,
  `app/Modules/Tenancy/Http/Requests/TenantRules.php`, `app/Modules/Tenancy/Http/Requests/StoreTenantRequest.php`
- Create: `resources/js/lib/slug.ts`, `resources/js/components/tenancy/tenant-form-fields.tsx`,
  `resources/js/components/tenancy/create-tenant-dialog.tsx`
- Modify: `routes/tenancy.php`, `TenantConsoleController`, `resources/js/pages/tenancy/index.tsx`,
  `app/Logging/RedactSensitiveData.php`, `lang/es/validation.php`
- Test: `tests/Feature/Tenancy/CreateTenantTest.php`, `tests/Feature/Tenancy/ReservedSlugsTest.php`,
  `tests/Feature/Logging/` (el test de redacción existente gana el caso `rfc`), `tests/js/lib/slug.test.ts`,
  `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Consumes: `Tenant`, Gate `manage-tenants`, `TenantConsoleController` (Tarea 2).
- Produces:
  - `final readonly class TenantData(string $legalName, string $tradeName, string $rfc, string $slug)`, ya normalizado.
  - `CreateTenant::handle(TenantData $data): Tenant`: crea, `Log::info('tenant.created', ['tenant_id' => $id])`,
    `RecordAudit::handle('tenant.created', $tenant)` sin metadata. Todo en `DB::transaction()`.
  - `TenantRules` (final, solo métodos estáticos):
    - `public const array RESERVED_SLUGS`: `admin`, `api`, `billing`, `companies`, `demo-requests`, `email`, `login`,
      `logout`, `password`, `register`, `settings`, `superadmin`, `two-factor-challenge`, `user`, más los que exija el
      test de rutas.
    - `rfc(?Tenant $ignore = null): array` → `required`, `string`, `regex:/\A[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}\z/u`, una
      closure que rechaza con "El RFC tiene una fecha que no existe." si `checkdate()` falla para AAMMDD (se prueban los
      dos siglos: válido si existe en 19AA o 20AA), y `Rule::unique('tenants', 'rfc')->ignore($ignore)`.
    - `slug(?Tenant $ignore = null): array` → `required`, `string`, `between:3,50`, `regex:/\A[a-z0-9]+(-[a-z0-9]+)*\z/`,
      `Rule::notIn(self::RESERVED_SLUGS)`, `Rule::unique('tenants', 'slug')->ignore($ignore)`.
    - `names(): array` → `legal_name` `required|string|max:200` y `trade_name` `required|string|max:120`, con la regex
      `SINGLE_LINE` de `StoreDemoRequestRequest`.
  - `StoreTenantRequest::prepareForValidation()`: RFC → `mb_strtoupper` sin espacios; slug → `trim` + `mb_strtolower`;
    nombres → `trim`. `toData(): TenantData`.
  - Ruta `POST /admin/companies` → `companies.store`; responde `redirect()->route('companies.index')` con el toast
    `Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa dada de alta.'])`.
  - Mensajes: RFC duplicado "Ya hay una empresa con este RFC."; slug duplicado "Esa dirección ya la usa otra empresa.";
    slug reservado "Esa dirección está reservada. Elige otra.". Atributos en `lang/es/validation.php`: `legal_name`
    'razón social', `trade_name` 'nombre comercial', `rfc` 'RFC', `slug` 'dirección'.
  - `lib/slug.ts`: `slugify(text: string): string` (NFD, quitar marcas diacríticas, minúsculas, todo lo que no sea `[a-z0-9]`
    → `-`, colapsar y recortar guiones, máximo 50). Debe coincidir con `Str::slug` en los casos del test.
  - Dialog "Dar de alta empresa": campos Razón social, Nombre comercial, RFC y Dirección (prefijo visible `/admin/`). La
    dirección se rellena con `slugify(nombre comercial)` mientras el usuario no la haya tocado.

- [ ] **Step 1: Feature tests (RED)** con `mutates(CreateTenant::class, StoreTenantRequest::class, TenantRules::class)`:
  - `it('creates a company that has not started its trial')`: post válido → 1 `Tenant` con los datos, `status()` NotStarted,
    redirección a `companies.index` con el toast.
  - `it('normalizes the RFC and the slug before checking them')`: `' gej010101 1a1 '` → `GEJ0101011A1`; `' Grupo-Ejemplo '` → `grupo-ejemplo`.
  - `it('accepts the RFC of a company and of a person')`: dataset `GEJ0101011A1` (12), `GOMA840229AB3` (13, 29 feb de 1984, bisiesto), `Ñ&A0101011A1`.
  - `it('rejects a malformed RFC')`: dataset 11 y 14 caracteres, `GEJ0113011A1` (mes 13), `GOMA850229AB3` (29 feb de 1985 y 2085, que no existe), letras en la fecha.
  - `it('rejects an RFC another company already has, whatever its case')`: existente `GEJ0101011A1`, envío `gej0101011a1` → error con el mensaje.
  - `it('rejects a duplicated, reserved or malformed slug')`: dataset `grupo-ejemplo` (existente), `settings`, `Grupo Ejemplo`, `ab`, 51 caracteres, `-acme`.
  - `it('requires every field')`.
  - `it('logs and audits the creation without the company data')`: `Log::spy()` → `shouldHaveReceived('info')->once()->with('tenant.created', ['tenant_id' => $id])`;
    `AuditEntry::sole()` con `event` `tenant.created`, `subject_id` el id, `tenant_id` `null` y `metadata` `[]`.
  - `it('answers 404 to anyone who is not a superadmin')` y nada se crea.
- [ ] **Step 2: Test de rutas reservadas (RED)** `ReservedSlugsTest.php`: `it('reserves every first segment the app uses under /admin')`
  recorre `Route::getRoutes()`, toma de cada URI que empieza con `admin/` su segundo segmento (sin parámetros `{…}`) y
  afirma que todos están en `TenantRules::RESERVED_SLUGS`. Ajusta la lista hasta que pase.
- [ ] **Step 3: Redacción (RED → GREEN):** en el test existente de `RedactSensitiveData`, agrega el caso `rfc` → `[REDACTED]`;
  agrega `'rfc'` a la lista con un comentario de una línea (el RFC de una persona física es dato personal).
- [ ] **Step 4:** backend según **Interfaces** → los tests de Feature pasan.
- [ ] **Step 5:** `tests/js/lib/slug.test.ts` (RED → GREEN): `Grupo Acme, S.A.` → `grupo-acme-sa`; `Ñandú Café` → `nandu-cafe`;
  `  --Hola   Mundo--  ` → `hola-mundo`; 80 caracteres → 50 sin guion final; `''` → `''`. Agrega en `CreateTenantTest` un
  dataset con los mismos casos de entrada contra `Str::slug()`, para que los dos lados no se separen.
- [ ] **Step 6:** Dialog y campos; botón "Dar de alta empresa" en el `PageHeader`.
- [ ] **Step 7: Browser:** `it('creates a company from the dialog')`: abre el Dialog, escribe "Grupo Ejemplo", verifica que la
  dirección quede en `grupo-ejemplo`, llena RFC y razón social, guarda, y ve la fila con "Sin iniciar" y el toast. Cubre
  también `visitPage(..., reach: abre el Dialog)` en claro y oscuro, para axe y CSP con el Dialog abierto.
- [ ] **Step 8:** `composer test`, `npm run test:js`, `npm run types:check` → verde.
- [ ] **Step 9: Commit** `Let the superadmin add a company with a valid, unique RFC and address`.

### Task 4: Editar una empresa

**Files:**
- Create: `app/Modules/Tenancy/Actions/UpdateTenant.php`, `app/Modules/Tenancy/Http/Requests/UpdateTenantRequest.php`,
  `resources/js/components/tenancy/edit-tenant-dialog.tsx`
- Modify: `routes/tenancy.php`, `TenantConsoleController`, `resources/js/components/tenancy/tenant-row.tsx`
- Test: `tests/Feature/Tenancy/UpdateTenantTest.php`, `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Consumes: `TenantData`, `TenantRules::rfc($tenant)`, `TenantRules::slug($tenant)`, `TenantRules::names()`, la
  normalización de `StoreTenantRequest` (sácala a un método protegido compartido o repítela con un helper en `TenantRules`;
  no la dupliques a mano), `tenant-form-fields.tsx`.
- Produces:
  - Ruta `PUT /admin/companies/{tenant}` → `companies.update`; `{tenant}` se resuelve por UUID (llave por defecto).
    Un id que no existe → 404.
  - `UpdateTenant::handle(Tenant $tenant, TenantData $data): Tenant`: guarda; si cambió algo, `Log::info('tenant.updated',
    ['tenant_id' => $id, 'changed' => [...]])` y `RecordAudit::handle('tenant.updated', $tenant, ['changed' => [...]])`.
    `changed` es la lista ordenada de **nombres** de columnas (`getChanges()` sin `updated_at`). Sin cambios → ni log ni bitácora.
  - Toast "Empresa actualizada.".
  - El Dialog "Editar empresa" llega con los datos de la fila; al cambiar la dirección muestra el aviso "Las direcciones
    anteriores de esta empresa dejarán de funcionar." Cambiar el nombre comercial **no** reescribe la dirección.

- [ ] **Step 1: Feature tests (RED)** con `mutates(UpdateTenant::class, UpdateTenantRequest::class)`:
  - `it('updates the company data')`.
  - `it('keeps its own RFC and slug without calling them duplicated')`: reenvía los mismos valores → sin errores.
  - `it('rejects the RFC or slug of another company')`.
  - `it('does not change the slug when the trade name changes')`.
  - `it('logs and audits the names of the changed fields, never their values')`: cambia `rfc` y `slug` →
    log `['tenant_id' => $id, 'changed' => ['rfc', 'slug']]`; `metadata` `['changed' => ['rfc', 'slug']]`; el RFC nuevo no
    aparece en `json_encode` de la fila de bitácora.
  - `it('records nothing when nothing changed')`.
  - `it('answers 404 to a non-superadmin and to an unknown company')`.
- [ ] **Step 2:** backend → PASS. **Step 3:** Dialog y botón "Editar" en cada fila (`aria-label` "Editar {nombre comercial}").
- [ ] **Step 4: Browser:** editar la razón social desde el Dialog y verla en la fila.
- [ ] **Step 5:** verificación completa → verde. **Step 6: Commit** `Let the superadmin correct a company's data`.

### Task 5: Activar y reactivar hasta una fecha

**Files:**
- Create: `app/Modules/Tenancy/Actions/ActivateTenant.php`, `app/Modules/Tenancy/Http/Requests/ActivateTenantRequest.php`,
  `resources/js/components/tenancy/activate-tenant-dialog.tsx`
- Modify: `routes/tenancy.php`, `TenantConsoleController`, `tenant-row.tsx`, `lang/es/validation.php`
- Test: `tests/Feature/Tenancy/ActivateTenantTest.php`, `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Consumes: `Tenant::status()`, `TenantRow.active_until`.
- Produces:
  - Ruta `POST /admin/companies/{tenant}/activate` → `companies.activate`.
  - `ActivateTenantRequest`: `active_until` `required`, `date_format:Y-m-d`, `after:` + hoy en `display_timezone`
    (`now(config('app.display_timezone'))->toDateString()`). Atributo `active_until` 'fecha de vigencia'.
    `activeUntil(): CarbonImmutable` = `CarbonImmutable::parse($value, display_timezone)->endOfDay()->utc()`.
  - `ActivateTenant::handle(Tenant $tenant, CarbonImmutable $until): Tenant`: `active_until = $until`, `deactivated_at = null`,
    no toca `trial_started_at`. `Log::info('tenant.activated', ['tenant_id' => $id, 'active_until' => $until->toIso8601String()])`
    y `RecordAudit` con `['active_until' => ...]` igual.
  - Toast "Empresa activa hasta {D MMM YYYY}.".
  - Dialog "Activar empresa" con `<input type="date">` (mínimo: mañana en hora de CDMX; valor inicial: `active_until` de la
    fila si es futura). El botón dice "Activar" o, si la empresa está bloqueada, "Reactivar".

- [ ] **Step 1: Feature tests (RED)** con `mutates(ActivateTenant::class, ActivateTenantRequest::class)`:
  - `it('activates a company until the end of the chosen day in Mexico City')`: `2026-12-31` → `active_until` igual a
    `2026-12-31 23:59:59.999999` CDMX en UTC (`2027-01-01 05:59:59.999999`); `status()` Active.
  - `it('measures today in Mexico City time')`: `travelTo('2026-10-28 03:00 UTC')` (27 oct, 21:00 en CDMX): `2026-10-28` se
    acepta y `2026-10-27` se rechaza.
  - `it('rejects today, past dates and malformed dates')`.
  - `it('reactivates a deactivated, expired or finished-trial company')`: dataset de los tres → Active y `deactivated_at` `null`.
  - `it('activates a company whose trial never started, without starting it')`: `trial_started_at` sigue `null`.
  - `it('logs and audits the new end date')`.
  - `it('answers 404 to a non-superadmin')`.
- [ ] **Step 2:** backend → PASS. **Step 3:** Dialog y botón por fila.
- [ ] **Step 4: Browser:** activar una empresa "Sin iniciar" eligiendo una fecha y ver "Activa" con "Activa hasta …".
- [ ] **Step 5:** verificación completa → verde. **Step 6: Commit** `Let the superadmin activate a company until a paid-through date`.

### Task 6: Desactivar

**Files:**
- Create: `app/Modules/Tenancy/Actions/DeactivateTenant.php`, `resources/js/components/tenancy/deactivate-tenant-dialog.tsx`
- Modify: `routes/tenancy.php`, `TenantConsoleController`, `tenant-row.tsx`
- Test: `tests/Feature/Tenancy/DeactivateTenantTest.php`, `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Consumes: `Tenant::status()->isBlocked()`, `TenantRow.blocked`.
- Produces:
  - Ruta `POST /admin/companies/{tenant}/deactivate` → `companies.deactivate`. Si la empresa ya está bloqueada, el
    controlador responde `abort(409)` y no cambia nada.
  - `DeactivateTenant::handle(Tenant $tenant): Tenant`: `deactivated_at = now()`; `Log::info('tenant.deactivated', ['tenant_id' => $id])`
    y `RecordAudit::handle('tenant.deactivated', $tenant)`.
  - Toast "Empresa desactivada.".
  - Dialog de confirmación "¿Desactivar {nombre comercial}?" con la descripción "Nadie de esta empresa podrá usar
    Humetría hasta que la actives con una fecha nueva. Sus datos se conservan." Si la empresa está en prueba o sin iniciar,
    agrega: "La prueba no se puede reanudar." El botón "Desactivar" solo aparece si `blocked` es falso.

- [ ] **Step 1: Feature tests (RED)** con `mutates(DeactivateTenant::class, TenantConsoleController::class)`:
  - `it('deactivates a company that is not started, in trial or active')`: dataset de los tres → Deactivated.
  - `it('refuses to deactivate a company that is already blocked')`: dataset de los tres bloqueados → 409, `deactivated_at`
    sin cambios, sin log ni bitácora.
  - `it('keeps the company and its data')`: tras desactivar, `Tenant::count()` igual y los campos intactos.
  - `it('logs and audits the deactivation')`; `it('answers 404 to a non-superadmin')`.
- [ ] **Step 2:** backend → PASS. **Step 3:** Dialog y botón.
- [ ] **Step 4: Browser:** desactivar una empresa activa y ver "Desactivada"; el botón ya no aparece en esa fila.
- [ ] **Step 5:** `composer ci:check` completo (incluye mutación) → verde.
- [ ] **Step 6: Commit** `Let the superadmin deactivate a company without losing its data`.

---

## Cierre del PR

- [ ] Llenar `.github/pull_request_template.md`: las 6 tareas como comportamientos, RED/GREEN de cada una, el checklist
  (Tenancy: `tenants` no lleva `tenant_id` porque es dato de la plataforma; el aislamiento A/B llega con el primer modelo
  con tenant).
- [ ] En "Riesgos y pendientes": el modal de bloqueo y el inicio de la prueba llegan con los PRs de invitar al owner y de
  resolver la empresa desde la URL; los *teams* de spatie necesitarán `team_id` `uuid`.
