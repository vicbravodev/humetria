# Primeros pasos guiados (`FirstSteps`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el owner de una empresa sin configurar vea un acompañamiento opcional (bienvenida, botón flotante con avance, panel de pasos, recorrido con spotlight y Humi) que se calcula de los datos y desaparece al terminar.

**Architecture:** Módulo nuevo `app/Modules/FirstSteps` que solo guarda `first_steps_states` (bienvenida, oculto, terminado) y calcula los pasos leyendo contratos de Tenancy, Organization, Campaigns, Telework, Seating, Attendance y Assistant. El kernel lo comparte como prop perezosa `firstSteps`; el front lo dibuja en `components/first-steps/*` con Radix Popover y `driver.js`. Humi lo lee con la herramienta `GetFirstSteps`.

**Tech Stack:** Laravel 13 / Pest / Inertia v3 + React 19 / TypeScript estricto / Postgres / Tailwind v4. **Una dependencia nueva, aprobada:** `driver.js@1.10.2` (MIT, sin dependencias).

**Spec:** `docs/superpowers/specs/2026-11-12-guided-onboarding-design.md`. Manda sobre este plan salvo en «Ajustes al spec».

## Ajustes al spec

Verificados contra el código; ninguno cambia lo que Victor aprobó ver, sí cómo se logra.

1. **Paso 1, «ficha completa»: no existe un centro incompleto.** `work_centers` tiene `NOT NULL` y `CHECK (btrim(x) <> '')` en calle, número, colonia, C.P., municipio, estado y actividad (`2026_10_29_100000_create_work_centers_table.php`), y la razón social y el RFC son obligatorios en `tenants`. Definición exacta: **hecho = la empresa tiene al menos un centro activo**. Se lee con `WorkCenters::all()` (ya existe); no hace falta método nuevo en `WorkCenters`. El recorrido pide revisar domicilio y actividad.
2. **Attendance no necesita contrato nuevo.** `AttendanceUsage::among([$tenantId])` ya responde «tiene política, pantalla, teléfono enlazado o marca». Hecho = `among([$tenantId]) !== []`.
3. **`FirstSteps::forUser(int $userId)`**, sin `tenantId`: todos los contratos que lee responden por la empresa del request (`CurrentTenant`); un `tenantId` distinto al actual daría pasos mezclados.
4. **El cierre se muestra una vez, y solo a quien usó los primeros pasos.** El spec pide a la vez «`null` con todo hecho» y «el panel muestra un cierre». Regla: con todo hecho se escribe `completed_at`; si el owner ya había visto la bienvenida y no los ocultó, esa carga regresa los pasos todos hechos (el front muestra el cierre) y la siguiente regresa `null`. Si nunca los vio o los ocultó, `null` desde esa misma carga (decisión 4).
5. **Interruptor `first_steps.enabled`** (`FIRST_STEPS_ENABLED`, `true` por defecto; `false` en `phpunit.xml`). Sin él, la bienvenida (un modal) aparecería en los 168 inicios de sesión de owner de `tests/Browser` y la prop sumaría consultas a 57 archivos con `expectsDatabaseQueryCount`. Los tests de `FirstSteps` lo prenden con `config(['first_steps.enabled' => true])`. No se toca ningún test existente salvo el de la lista de herramientas de Humi (Task 12).
6. **«También tienes»** lista las otras herramientas *del orden del paso 3* que la empresa tiene (`nom035`, `nom037`, `seating`, `attendance`), como el ejemplo del spec. Vacaciones, organigrama, quejas, pulso y clima no entran: no todas tienen una página de inicio con nombre.
7. **`BuildFirstSteps` se prueba en Feature**, no en Unit: arma enlaces con `route()` y lee contratos reales; los tests Unit del proyecto no levantan la app.
8. **Orden:** los contratos nuevos (Task 2) van antes de `BuildFirstSteps` (Task 3), que los consume.
9. **Archivos que el spec no lista:** `Support/CompanyFirstSteps.php` (implementa el contrato), `Data/OtherTool.php`, `components/first-steps/first-steps-dock.tsx` y `first-steps-tour.tsx` (+ `.css`), `lib/first-steps/{open,dashboard,tour}.ts`, `config/first_steps.php`. Los iconos de cada paso viven en `first-step-item.tsx`, no en `lib` (`lib` no importa componentes).

## Global Constraints

- TDD estricto: test, verlo fallar por la razón correcta, código mínimo. Un commit = un comportamiento, tests en verde en cada commit, a nombre de Victor y sin atribución a IA. Mensajes en español: `Primeros pasos: …`.
- Sin `@phpstan-ignore`, `assert()`, `@var` inline ni casts para callar; sin `as`, `!`, `any`, `@ts-ignore`. Sin `->skip()`, `->todo()` ni aflojar aserciones.
- Todo lo nuevo se llama `FirstSteps` / `first-steps` / `first_steps`. «Onboarding» sigue siendo `tenancy/onboarding`, que no se modifica salvo montar el recorrido (Task 10).
- Solo el **owner**; por empresa; siempre omitible; el avance **no se guarda**, se calcula.
- Otros módulos se leen solo por `Contracts`/`Data`. `Actions` y `Models` sin HTTP. Clases `final`, `declare(strict_types=1)`. El rol se compara por valor (`$member->role->value === 'owner'`), sin importar `Tenancy\Enums`.
- `first_steps_states`: `id` uuid v4, `tenant_id`, `user_id`, `welcomed_at`, `hidden_at`, `completed_at` y timestamps, todo `timestampTz`; `unique(tenant_id, user_id)`; llaves foráneas. Modelo con `BelongsToCurrentTenant`.
- Logs `first_steps.welcomed`, `first_steps.hidden`, `first_steps.shown`, `first_steps.completed` con `tenant_id` y `user_id` en el contexto. Nada a la bitácora.
- Rutas `tenant.first-steps.{welcome,hide,show}`, POST bajo `admin/{tenant:slug}/first-steps/*`, dentro del grupo de `routes/company.php`. No owner: 403. Otra empresa: 404.
- Privacidad: ningún paso lee respuestas ni nada individual; a Humi le llegan solo títulos, estado y enlace.
- UI en es-MX, el panel tutea. Color solo por tokens. Sin `<style>` inline. Los recorridos se anclan a `data-tour="…"`, nunca a clases.
- Límites del front: 300 líneas por archivo, 100 por componente, complejidad 10, 3 parámetros, un componente por archivo, kebab-case, imports con `@/`. `components/first-steps` no importa `components/assistant` ni `components/tenancy` (ni al revés): se hablan por eventos de `lib`.
- Mientras se trabaja: `php artisan test --compact <archivo>`, `vendor/bin/pint --dirty`, `vendor/bin/phpstan`, `npm run types:check`, `npm run test:js`. Navegador: `npm run build && composer test:browser`. Al cerrar: `composer ci:check`. Las clases con lógica declaran `mutates()`; **la mutación no se corre**.
- Referencias de diseño: tablero **V2-Panel** (`docs/rediseno/MAPA.md`; tarjeta «Siguiente paso recomendado», `NextStepCard`) para el panel y sus textos; tablero **V3-Onboarding** para la lista de pasos con palomita; pantalla **Panel** de `docs/design-system/pantallas/` para tarjeta, tipografía y estado vacío con siguiente paso; el lanzador y el panel de Humi (`components/assistant/humi-launcher.tsx`, `humi-header.tsx`) para el botón flotante, su sombra `shadow-float` y su foco.

## Review Focus

1. **Empresa en solo lectura, bloqueada o sindicato:** los POST darían 423 y un sindicato no tiene estas herramientas → `firstSteps` es `null` y no se escribe nada. → Task 4.
2. **Owner que deja de serlo con su fila ya creada** (lo bajan a `hr`): `null`, sin botón ni menú. → Task 4.
3. **Superadmin impersonando al owner:** la bienvenida no se abre sola (no la consume por él); el botón sí está. → Tasks 9 y 13.
4. **El control del recorrido no está** (375 px, otra vista) **o `sessionStorage` falla o guarda un recorrido de otra página:** burbuja centrada sin spotlight, nunca colgado; la llave vieja se borra al leerla. → Tasks 7 y 8.
5. **Dos pestañas o doble clic en Empezar / Ocultar:** una sola fila, `welcomed_at` conserva la primera hora, un solo log. → Task 5.
6. **Empresa sin herramienta del paso 3 y con Humi apagado:** 3 pasos; la bienvenida dice el número real, no «5». → Tasks 3 y 7.
7. **Paso 3 de NOM-037 o Ley Silla sin ningún centro:** Guíame lleva a Centros de trabajo, no a la primera vez vacía. → Task 3.

---

## Estructura de archivos

```
app/Modules/FirstSteps/
  Contracts/FirstSteps.php            forUser(int $userId): ?FirstStepsData
  Data/{FirstStepsData,FirstStep,OtherTool}.php
  Actions/{BuildFirstSteps,MarkWelcomed,HideFirstSteps,ShowFirstSteps,CompleteFirstSteps}.php
  Support/CompanyFirstSteps.php       implementa el contrato
  Models/FirstStepsState.php
  Policies/FirstStepsStatePolicy.php
  Http/Controllers/FirstStepsStateController.php
  Providers/FirstStepsServiceProvider.php   (alta en bootstrap/providers.php, antes de AssistantServiceProvider)
config/first_steps.php  ·  routes/first_steps.php  ·  database/factories/FirstSteps/FirstStepsStateFactory.php
database/migrations/2027_01_03_100000_create_first_steps_states_table.php
resources/js/components/first-steps/{first-steps-dock,first-steps-launcher,first-steps-panel,first-step-item,first-steps-welcome,first-steps-done,first-steps-tour}.tsx + first-steps-tour.css
resources/js/hooks/use-first-steps-tour.ts
resources/js/lib/first-steps/{steps,tour-session,tour,open,dashboard}.ts  ·  resources/js/types/first-steps.ts
tests/Feature/FirstSteps/*  ·  tests/js/lib/first-steps/*  ·  tests/Browser/FirstStepsTest.php
```

Contratos que se agregan en otros módulos (Task 2): `Tenancy\Contracts\Members::pendingInvitations(): int`, `Campaigns\Contracts\CampaignRecords::anyLaunched(): bool`, `Assistant\Contracts\HumiActivity::hasAsked(int $userId): bool`.

---

### Task 1: Tabla, modelo y factory de `FirstStepsState`

**Files:**
- Create: la migración, `Models/FirstStepsState.php`, `database/factories/FirstSteps/FirstStepsStateFactory.php`, `Providers/FirstStepsServiceProvider.php`, `config/first_steps.php`
- Modify: `bootstrap/providers.php`, `.env.example` (`FIRST_STEPS_ENABLED=true`), `phpunit.xml` (`<env name="FIRST_STEPS_ENABLED" value="false"/>`)
- Test: `tests/Feature/FirstSteps/FirstStepsStateTest.php`

**Interfaces — Produces:**
- `FirstStepsState` (`#[Fillable(['tenant_id','user_id','welcomed_at','hidden_at','completed_at'])]`, `#[UseFactory]`, `BelongsToCurrentTenant, HasFactory, HasVersion4Uuids`; casts `immutable_datetime`). Factory con estados `welcomed()`, `hidden()`, `completed()`.
- `config('first_steps.enabled')`: `(bool) env('FIRST_STEPS_ENABLED', true)`.
- Migración: `foreignUuid('tenant_id')->constrained()->restrictOnDelete()`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, tres `timestampTz` nulos, `timestampsTz()`, `unique(['tenant_id','user_id'])`.

- [ ] **Step 1: tests que fallan** (seguir `tests/Feature/Assistant/ParticipantTest.php` para el estilo)

```php
it('keeps one state per company and user', function (): void {
    // dos filas con el mismo tenant y user → QueryException (unique)
});
it('hides the state of company A from company B', function (): void {
    // workIn($a); crear; workIn($b); FirstStepsState::query()->count() === 0 y ->find($id) === null
});
it('has the switch off in the test environment and on by default', function (): void {
    expect(config('first_steps.enabled'))->toBeFalse();
    expect((require base_path('config/first_steps.php'))['enabled'])->toBeBool();
});
```

- [ ] **Step 2:** `php artisan test --compact tests/Feature/FirstSteps/FirstStepsStateTest.php` → FAIL (la clase no existe).
- [ ] **Step 3:** crear migración, modelo, factory, provider vacío registrado y config.
- [ ] **Step 4:** el archivo y `tests/Feature/TenantScopedModelsTest.php`, `tests/Feature/TimestampColumnsTest.php`, `tests/Unit/EnvExampleTest.php`, `tests/Unit/ArchitectureTest.php` → PASS. `vendor/bin/pint --dirty && vendor/bin/phpstan`.
- [ ] **Step 5:** commit `Primeros pasos: tabla first_steps_states, su modelo y el interruptor de configuración`.

### Task 2: Tres lecturas nuevas en contratos de otros módulos

Un commit por contrato; cada test declara `mutates()` de la clase que implementa.

**Files:**
- Modify: `Tenancy/Contracts/Members.php`, `Tenancy/Support/CompanyMembers.php`; test `tests/Feature/Tenancy/MembersTest.php` (agregar casos)
- Modify: `Campaigns/Contracts/CampaignRecords.php`, `Campaigns/Support/CompanyCampaignRecords.php`; test en el archivo de `tests/Feature/Campaigns` que ya prueba `CampaignRecords`
- Create: `Assistant/Contracts/HumiActivity.php`, `Assistant/Support/TenantHumiActivity.php`; Modify: `AssistantServiceProvider` (bind), `Assistant/README.md` («Lo que Assistant expone»); test `tests/Feature/Assistant/HumiActivityTest.php`

**Interfaces — Produces:**
- `Members::pendingInvitations(): int` — invitaciones de la empresa actual sin aceptar, sin cancelar y con `expires_at > now()`. `TenantInvitation` no tiene scope: filtrar por `tenant_id = CurrentTenant::id()`. Una consulta.
- `CampaignRecords::anyLaunched(): bool` — existe una campaña de la empresa actual en `active` o `closed` (cualquier tipo). Una consulta `exists()`.
- `HumiActivity::hasAsked(int $userId): bool` — existe una fila de `ai_usage` de la empresa actual cuyo `participant_id` es el `ConversationParticipant` de ese usuario. Una consulta. Se lee de `ai_usage` y no de las conversaciones porque la persona puede borrarlas.

- [ ] **Step 1: tests que fallan**

```php
// Members
it('counts the open invitations of the company, not the accepted, cancelled, expired or another company\'s');
//   1 abierta + 1 aceptada + 1 cancelada + 1 con expires_at = now() + 1 de otra empresa → 1
// CampaignRecords
it('says whether any campaign was launched', function (): void {
    // sin campañas → false; solo borrador → false; activa → true; cerrada → true; activa de otra empresa → false
});
// HumiActivity
it('says whether the person has asked Humi in this company', function (): void {
    // sin uso → false; AiUsage con participant de otro usuario → false; del usuario → true;
    // el mismo usuario con uso en otra empresa y workIn($a) → false; uso sin participant (record() de otro módulo) → false
});
```

- [ ] **Step 2:** correr cada archivo → FAIL (método no definido).
- [ ] **Step 3:** implementar las tres firmas.
- [ ] **Step 4:** los tres archivos → PASS; Pint y PHPStan.
- [ ] **Step 5:** tres commits: `Usuarios: Members cuenta las invitaciones pendientes`, `Campañas: CampaignRecords dice si ya se lanzó alguna`, `Humi: HumiActivity dice si la persona ya le preguntó algo`.

### Task 3: `BuildFirstSteps` y sus datos

**Files:**
- Create: `Data/FirstStep.php`, `Data/OtherTool.php`, `Data/FirstStepsData.php`, `Actions/BuildFirstSteps.php`
- Modify: `tests/Unit/ActionsLogTest.php` (`ACTIONS_WITHOUT_OWN_LOG`: `'app/Modules/FirstSteps/Actions/BuildFirstSteps.php' => 'reads the steps of a company; changes nothing'`)
- Test: `tests/Feature/FirstSteps/BuildFirstStepsTest.php` (`mutates(BuildFirstSteps::class)`)

**Interfaces:**
- Consumes: `WorkCenters::all()`, `Headcount::ofCompany()`, `CompanyModules::enabledKeys()`, `CampaignRecords::anyLaunched()`, `TeleworkApplicability::declarations()`, `SeatingApplicability::declarations()`, `AttendanceUsage::among()`, `Members::all()` + `pendingInvitations()`, `AiAccess::available()`, `HumiActivity::hasAsked()`, `Companies::current()->slug`.
- Produces:
  - `final readonly class FirstStep(public string $key, public bool $done, public ?string $href, public ?string $tool = null)`
  - `final readonly class OtherTool(public string $key, public string $href)`
  - `final readonly class FirstStepsData(public array $steps /* list<FirstStep> */, public bool $welcomed, public bool $hidden, public array $others /* list<OtherTool> */)` con `allDone(): bool`, `withState(bool $welcomed, bool $hidden): self` y `toArray(): array{steps: list<array{key: string, done: bool, href: string|null, tool: string|null}>, welcomed: bool, hidden: bool, others: list<array{key: string, href: string}>}`.
  - `BuildFirstSteps::handle(string $tenantId, int $userId): FirstStepsData` (regresa `welcomed: false, hidden: false`).

Valores exactos (enlaces relativos, `route(..., absolute: false)`):

| key | tool | hecho cuando | ruta de `href` |
|---|---|---|---|
| `work_centers` | — | algún centro de `all()` con `archived === false` | `tenant.work-centers.index` |
| `people` | — | `ofCompany($tenantId) >= 1` | `tenant.employees.index` |
| `evaluation` | `nom035` | `anyLaunched()` | `tenant.campaigns.index` |
| `evaluation` | `nom037` | `declarations($idsActivos) !== []` | `tenant.work-centers.onboarding`; sin centros activos, `tenant.work-centers.index` |
| `evaluation` | `seating` | igual, con `SeatingApplicability` | igual que `nom037` |
| `evaluation` | `attendance` | `among([$tenantId]) !== []` | `tenant.attendance.setup` |
| `team` | — | `count(all()) > 1 \|\| pendingInvitations() > 0` | `tenant.members.index` |
| `humi` | — | `hasAsked($userId)` | `null` (el recorrido corre en la página abierta) |

Orden: el de la tabla. `evaluation` usa la primera de `['nom035','nom037','seating','attendance']` que esté en `enabledKeys()`; sin ninguna, no hay paso. `humi` solo si `AiAccess::available()`. `others`: las demás de esa lista que la empresa tiene, en ese orden, con `tenant.campaigns.index`, `tenant.telework.index`, `tenant.seating.index`, `tenant.attendance.index`. Solo se consulta la herramienta elegida (no las cuatro).

- [ ] **Step 1: tests que fallan** — `workIn($tenant)` con un owner (`ownerOf`); módulos con `resolve(CompanyModules::class)->syncPlan($tenant->id, [...], CarbonImmutable::now())`.

```php
it('gives the five steps, none done, to a company with every module and nothing loaded');
//   keys === ['work_centers','people','evaluation','team','humi']; tool del tercero === 'nom035'; todos done === false
it('marks each step done from the data', /* dataset: un caso por paso */);
//   centro activo → work_centers; solo un centro archivado → no; empleado activo → people; solo desactivado → no;
//   campaña activa → evaluation; segundo miembro → team; invitación abierta → team; AiUsage del owner → humi
it('takes the first tool the plan has for the third step', function (array $modules, ?string $tool, bool $done): void {})->with([
    'nom035 primero' => [['nom035','nom037','seating','attendance'], 'nom035', /* campaña cerrada */ true],
    'nom037' => [['nom037','seating','vacations'], 'nom037', /* TeleworkApplicability->declare(...) */ true],
    'seating' => [['seating','attendance'], 'seating', true],
    'attendance' => [['attendance','vacations'], 'attendance', /* AttendancePolicy::factory() */ true],
    'ninguna' => [['vacations','org_chart'], null, false],   // 4 pasos, sin evaluation
]);
it('lists the other tools of the third step the plan has, never the main one');   // ['nom035','nom037','seating'] → others keys ['nom037','seating']
it('leaves Humi out when it is off for the company');   // TenantAiSettings enabled=false → sin 'humi'
it('gives three steps to a company with no evaluation tool and Humi off');
it('sends the questions step to the work centers while there is none', /* nom037 sin centros → href termina en /work-centers */);
it('links every step inside the company', /* cada href no nulo empieza con "/admin/{$tenant->slug}/" */);
it('never reads another company', /* todo hecho en B, workIn(A) → nada hecho */);
```

- [ ] **Step 2:** FAIL (clase no existe). **Step 3:** implementar. **Step 4:** PASS + `tests/Unit/ActionsLogTest.php`, `tests/Unit/ArchitectureTest.php`; Pint, PHPStan.
- [ ] **Step 5:** commit `Primeros pasos: BuildFirstSteps arma los pasos de la empresa según su plan y sus datos`.

### Task 4: El contrato `FirstSteps` y cuándo se muestra

**Files:**
- Create: `Contracts/FirstSteps.php`, `Support/CompanyFirstSteps.php`, `Actions/CompleteFirstSteps.php`; Modify: `FirstStepsServiceProvider` (`bind(FirstSteps::class, CompanyFirstSteps::class)`)
- Test: `tests/Feature/FirstSteps/FirstStepsContractTest.php` (`mutates(CompanyFirstSteps::class, CompleteFirstSteps::class)`; `beforeEach`: `config(['first_steps.enabled' => true])`)

**Interfaces:**
- Consumes: `BuildFirstSteps::handle()`, `Members::find()`, `Companies::current()` (`blocked`, `readOnly`, `isUnion()`), `CurrentTenant::id()`.
- Produces: `FirstSteps::forUser(int $userId): ?FirstStepsData`; `CompleteFirstSteps::handle(string $tenantId, int $userId): void` (crea o actualiza la fila con `completed_at = now()`, log `first_steps.completed`).

`forUser`, en este orden (cada salida es `null`):
1. `config('first_steps.enabled')` apagado.
2. Fila del usuario con `completed_at` → `null` con **una** consulta.
3. No es owner (`find($userId)?->role->value !== 'owner'`).
4. Empresa `blocked`, `readOnly` o sindicato.
5. `BuildFirstSteps`. Si `allDone()`: `CompleteFirstSteps`; regresa los pasos solo si la fila tenía `welcomed_at` y no `hidden_at` (el cierre), si no `null`.
6. Si no, los pasos con `withState(welcomed, hidden)` de la fila (sin fila: `false, false`).

- [ ] **Step 1: tests que fallan**

```php
it('is null while the switch is off');
it('gives the owner the steps with the welcome unseen and not hidden');
it('is null for hr and viewer', /* dataset TenantRole::Hr, Viewer */);
it('is null for an owner who was made hr, even with a state row');                       // Review Focus 2
it('is null and writes nothing for a read-only, a blocked or a union company', /* dataset; assertDatabaseCount('first_steps_states', 0) */);  // Review Focus 1
it('carries the welcomed and hidden marks of the state row');
it('writes completed_at and is null when everything is done and the owner never saw the welcome');
it('gives the finished steps once to an owner who was following them, then null', function (): void {
    // fila welcomed; todo hecho → primera llamada: allDone() true y completed_at escrito; segunda: null
});
it('is null at once when everything is done and the owner had hidden them');
it('costs one query once completed', fn () => /* $this->expectsDatabaseQueryCount(1) alrededor de forUser() */);
it('keeps each company of the same owner apart', /* owner de A y B: welcomed en A no afecta B */);
it('logs first_steps.completed with the company and the user, once');
```

- [ ] **Step 2:** FAIL. **Step 3:** implementar. **Step 4:** PASS; Pint, PHPStan.
- [ ] **Step 5:** commit `Primeros pasos: el contrato decide a quién se le muestran y los da por terminados`.

### Task 5: Bienvenida, ocultar y mostrar

**Files:**
- Create: `Actions/MarkWelcomed.php`, `Actions/HideFirstSteps.php`, `Actions/ShowFirstSteps.php`, `Policies/FirstStepsStatePolicy.php`, `Http/Controllers/FirstStepsStateController.php`, `routes/first_steps.php`
- Modify: `routes/company.php` (`require __DIR__.'/first_steps.php';` antes de `assistant.php`), `Tenancy/Support/ModuleRoutes.php` (`always()`: `'tenant.first-steps.*'`), `FirstStepsServiceProvider` (`Gate::policy(FirstStepsState::class, FirstStepsStatePolicy::class)`)
- Test: `tests/Feature/FirstSteps/FirstStepsRoutesTest.php` (`mutates` de las tres acciones y la policy)

**Interfaces — Produces:**
- `MarkWelcomed|HideFirstSteps|ShowFirstSteps::handle(string $tenantId, int $userId): void`. Las tres hacen `FirstStepsState::query()->firstOrCreate(['tenant_id' => …, 'user_id' => …])` dentro de `DB::transaction` y solo escriben y registran el log si algo cambia: `MarkWelcomed` pone `welcomed_at` si es nulo; `Hide` pone `hidden_at` si es nulo; `Show` lo limpia si no lo es.
- `FirstStepsStatePolicy::manage(User $user): bool` — owner de la empresa actual (`Members::find`).
- Controlador con `welcome`, `hide`, `show`: `Gate::authorize('manage', FirstStepsState::class)`, acción, `back()`. `hide` hace `Inertia::flash('toast', ['type' => 'success', 'message' => 'Puedes retomarlos desde tu menú de usuario.'])`.
- Rutas con el mismo patrón `->where('tenant', $notReserved)` que `routes/attendance.php`: `POST admin/{tenant:slug}/first-steps/welcome|hide|show` → `tenant.first-steps.welcome|hide|show`.

- [ ] **Step 1: tests que fallan**

```php
it('marks the welcome as seen for the owner in that company only');
it('keeps the first welcomed_at and one row when it is sent twice');           // Review Focus 5; Log::spy(): 'first_steps.welcomed' una vez
it('hides and shows again', /* hide → hidden_at no nulo y flash toast; show → nulo */);
it('answers 403 to hr and viewer', /* dataset de las tres rutas × dos roles; sin fila creada */);
it('answers 404 for a company the person does not belong to');
it('sends a guest to the login');
it('logs first_steps.welcomed, first_steps.hidden and first_steps.shown with tenant_id and user_id');
```

- [ ] **Step 2:** FAIL (ruta no definida). **Step 3:** implementar. **Step 4:** PASS + `tests/Feature/Tenancy/ModuleGateTest.php`, `tests/Feature/RouteSecurityTest.php`, `tests/Unit/ActionsLogTest.php`; Pint, PHPStan.
- [ ] **Step 5:** commit `Primeros pasos: rutas de bienvenida, ocultar y mostrar, solo para el owner`.

### Task 6: Prop compartida `firstSteps`

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/global.d.ts`; Create: `resources/js/types/first-steps.ts`
- Test: `tests/Feature/FirstSteps/FirstStepsSharedPropTest.php`

**Interfaces:**
- Consumes: `FirstSteps::forUser()`, `FirstStepsData::toArray()`.
- Produces (kernel): `'firstSteps' => fn (): ?array => $this->firstSteps($request)` — `null` sin empresa compartida, sin usuario o si `tenant.role !== 'owner'` (se lee de `Inertia::getShared('tenant')` como `seesNom035()`: cero consultas para quien no es owner); si no, `app(FirstSteps::class)->forUser($user->id)?->toArray()`.
- Produces (TS):

```ts
export type FirstStepKey = 'work_centers' | 'people' | 'evaluation' | 'team' | 'humi';
export type FirstStepTool = 'nom035' | 'nom037' | 'seating' | 'attendance';
export type FirstStep = { key: FirstStepKey; done: boolean; href: string | null; tool: FirstStepTool | null };
export type FirstSteps = { steps: FirstStep[]; welcomed: boolean; hidden: boolean; others: { key: FirstStepTool; href: string }[] };
// global.d.ts → sharedPageProps: firstSteps: FirstSteps | null;
```

- [ ] **Step 1: tests que fallan** (contra `GET /admin/{slug}/members`, con el interruptor prendido)

```php
it('shares the steps with the owner inside the company', /* assertInertia: has('firstSteps.steps', 5)->where('firstSteps.welcomed', false) */);
it('shares null to hr and viewer, at no query', /* mismo número de consultas con el interruptor prendido y apagado */);
it('shares null outside a company', /* GET /admin/companies como superadmin */);
it('shares null and writes completed_at once every step is done');
it('keeps the two companies of one owner apart');
it('never shares a name, an email or an answer', /* json_encode de la prop no contiene el nombre ni el correo del empleado ni del invitado */);
const FIRST_STEPS_QUERIES = /* el número medido: ≤ 12 */;
it('costs the same queries with one work center and with thirty', /* diferencia de consultas prendido − apagado === FIRST_STEPS_QUERIES en ambos casos */);
```

- [ ] **Step 2:** FAIL. **Step 3:** implementar. **Step 4:** PASS + `tests/Unit/ArchitectureTest.php` (el kernel solo usa el contrato) y `npm run types:check`.
- [ ] **Step 5:** commit `Primeros pasos: el panel comparte los pasos del owner como prop perezosa`.

### Task 7: Lógica del front en `lib/first-steps`

**Files:**
- Create: `resources/js/lib/first-steps/{steps,tour-session,open,dashboard}.ts` (`tour.ts` va en Task 8: necesita el tipo de `driver.js`)
- Test: `tests/js/lib/first-steps/{steps,tour-session,open,dashboard}.test.ts`

**Interfaces — Produces:**
- `steps.ts`
  - `stepCopy(step: FirstStep): { title: string; text: string }`
  - `progress(steps: FirstStep[]): { done: number; total: number; label: string }` → `label` = `"2 de 5"`; `progressText(...)` = `"2 de 5 pasos"`.
  - `welcomeText(total: number): string` → `"Son 5 pasos y unos 15 minutos. Puedes dejarlo cuando quieras y retomarlo desde el botón de abajo."` con el número real (`"Son 3 pasos…"`).
  - `otherToolLabel(key: FirstStepTool): string` → `NOM-035`, `NOM-037`, `Ley Silla`, `Registro de jornada`.
  - `humiQuestion(modules: string[] | undefined): string` → con `nom035`: `"¿Qué me falta para cumplir con la NOM-035?"`; sin él: `"¿Qué pendientes tengo primero?"`.
  - `bubblesOf(step: FirstStep): TourBubble[]`, `type TourBubble = { target: string; title: string; text: string }` (`target` es el valor de `data-tour`).
- `tour-session.ts`: `rememberTour(key: FirstStepKey): void`, `takeTour(): FirstStepKey | null` (lee y borra; `sessionStorage` en `try/catch`; un valor que no es una llave → `null`). Llave: `humetria:first-steps-tour`.
- `open.ts`: `OPEN_FIRST_STEPS = 'first-steps:open'`, `openFirstSteps(): void` (como `lib/assistant/open-humi.ts`).
- `dashboard.ts`: `repeatsEvaluationStep(firstSteps: FirstSteps | null, recommendation: 'questions' | NormKey | null): boolean` — `true` solo si `firstSteps` no es `null`, no está oculto, su paso `evaluation` está pendiente y: `recommendation === 'questions'` con `tool` `nom037` o `seating`, o `recommendation === tool`.

Textos (título / línea):

| paso | título | línea |
|---|---|---|
| `work_centers` | Revisa tus centros de trabajo | Su domicilio y su actividad salen en tus informes. |
| `people` | Carga a tu gente | Importa tu plantilla con un CSV o agrégala a mano. |
| `evaluation`·`nom035` | Lanza tu primera evaluación NOM-035 | Elige un centro y envía las guías. |
| `evaluation`·`nom037` | Dinos a qué centros les aplica NOM-037 | Dos preguntas por centro. |
| `evaluation`·`seating` | Dinos a qué centros les aplica Ley Silla | Dos preguntas por centro. |
| `evaluation`·`attendance` | Configura el registro de jornada | Zonas, horarios y política para la primera marca. |
| `team` | Invita a alguien de tu equipo | RH o quien te ayude con las rutas. |
| `humi` | Conoce a Humi | Hazle tu primera pregunta. |

Burbujas (`target` → título · texto):

| paso | burbujas |
|---|---|
| `work_centers` | `add-work-center` → «Tus centros de trabajo» · «Agrega cada lugar donde trabaja tu gente. Las normas se aplican por centro.»; `work-centers-list` → «Revisa la ficha» · «El domicilio y la actividad principal salen tal cual en tus informes.» |
| `people` | `import-employees` → «Importa tu plantilla» · «Sube un CSV con tu gente; te decimos qué filas corregir antes de guardar.»; `add-employee` → «O una por una» · «Agrega a una persona a mano.» |
| `evaluation`·`nom035` | `new-evaluation` → «Tu primera evaluación» · «Elige el centro; Humetria decide qué guías tocan según su número de personas.» |
| `evaluation`·`nom037`/`seating` | `applicability-questions` → «Dos preguntas por centro» · «Con tus respuestas armamos las rutas y las constancias de lo que no aplica.» |
| `evaluation`·`attendance` | `attendance-setup` → «Puesta en marcha» · «Aquí ves qué le falta a cada centro, horario y teléfono para la primera marca.» |
| `team` | `invite-member` → «Invita a tu equipo» · «Elige qué puede hacer cada quien: RH, solo consulta o la Comisión.» |
| `humi` | `humi-launcher` → «Este es Humi» · «Pregúntale por tus pendientes, tus plazos o qué dice la norma.» |

- [ ] **Step 1: tests que fallan** — un caso por fila de las dos tablas (`toEqual` con el texto exacto), más:

```ts
it('counts the steps done', () => expect(progress(steps).label).toBe('2 de 5'));
it('says the real number of steps in the welcome', () => expect(welcomeText(3)).toMatch(/^Son 3 pasos/));   // Review Focus 6
it('remembers a tour once', () => { rememberTour('people'); expect(takeTour()).toBe('people'); expect(takeTour()).toBeNull(); });
it('gives no tour when sessionStorage throws or holds something else');                                      // Review Focus 4
it('says when the panel card would repeat the third step', /* tabla de verdad de repeatsEvaluationStep: null, oculto, hecho, questions×4 tools, norma igual y distinta */);
it('asks to open the panel with a window event');
```

- [ ] **Step 2:** `npm run test:js` → FAIL. **Step 3:** implementar. **Step 4:** PASS con cobertura ≥ 90 %; `npm run types:check`.
- [ ] **Step 5:** commit `Primeros pasos: textos, avance y sesión del recorrido en lib/first-steps`.

### Task 8: `driver.js` y el recorrido

**Files:**
- Modify: `package.json` + lockfile (`npm install --save-exact driver.js@1.10.2`)
- Create: `resources/js/lib/first-steps/tour.ts` + `tests/js/lib/first-steps/tour.test.ts`, `resources/js/components/first-steps/first-steps-tour.css`, `first-steps-tour.tsx`, `resources/js/hooks/use-first-steps-tour.ts`

**Sobre la versión y la CSP (verificado con el paquete):** `1.10.2` (2026-10-18) es la última con más de dos semanas publicada; `1.12.1` salió hoy. Sin dependencias, MIT, exporta `driver.js/dist/driver.css`. No crea `<style>` ni usa `setAttribute('style')`: fija estilos por CSSOM (`el.style.x`), que la CSP con nonce permite. Escribe título y texto con `innerHTML`: **solo se le pasan los textos fijos de `lib/first-steps/steps.ts`, nunca un dato de la empresa**.

**Interfaces:**
- Consumes: `takeTour()`, `bubblesOf()`, `driverSteps()`.
- Produces:
  - `lib/first-steps/tour.ts`: `driverSteps(bubbles: TourBubble[], exists: (selector: string) => boolean): DriveStep[]` (`import type { DriveStep } from 'driver.js'`) — cada burbuja da `{ element: '[data-tour="…"]', popover: { title, description } }`; sin `element` cuando `exists` dice que no (driver.js centra la burbuja).
  - `useFirstStepsTour(): { start: (step: FirstStep) => void }` — al montar y en cada cambio de `usePage().url`: `takeTour()`; si hay llave y el paso existe en `usePage().props.firstSteps`, corre su recorrido. `start(step)`: con `href` distinto de la URL actual → `rememberTour(step.key)` y `router.visit(step.href)`; si no, lo corre ahí.
  - Correr = `const { driver } = await import('driver.js')` (fuera del bundle inicial) y `driver({ popoverClass: 'first-steps-tour', animate: !reducedMotion, allowClose: true, showProgress: steps.length > 1, progressText: '{{current}} de {{total}}', nextBtnText: 'Siguiente', prevBtnText: 'Anterior', doneBtnText: 'Entendido', steps: driverSteps(bubbles, (s) => document.querySelector(s) !== null) }).drive()`. Se destruye al desmontar y antes de una navegación.
  - `FirstStepsTour` (componente sin marcado): importa el CSS y llama al hook; es lo que montan el layout y `tenancy/onboarding`.
- CSS: `@import 'driver.js/dist/driver.css';` (patrón de `components/organization/org-flow.css`) y, sobre `.driver-popover.first-steps-tour`, los tokens: `background: var(--popover)`, `color: var(--popover-foreground)`, borde `var(--border)`, radio y sombra del popover del design system, fuente heredada, botones como `Button` (`--primary` / `--primary-foreground`, foco `outline: 2px solid var(--ring)`). El velo usa el mismo color que el de `components/ui/dialog.tsx`. Sin colores crudos nuevos; con `prefers-reduced-motion` sin transición.

- [ ] **Step 1:** instalar (commit aparte). Test que falla en `tour.test.ts`:

```ts
it('points each bubble at its control', () => expect(driverSteps(b, () => true)[0]).toMatchObject({ element: '[data-tour="import-employees"]', popover: { title: 'Importa tu plantilla' } }));
it('centers a bubble whose control is not on the page', () => expect(driverSteps(b, () => false)[0]).not.toHaveProperty('element'));   // Review Focus 4
```

  `npm run test:js` → FAIL; implementar `tour.ts` → PASS.
- [ ] **Step 2:** escribir CSS, hook y componente. El test que lo exige es de navegador (Task 13); aquí basta `npm run check && npm run types:check && npm run build`, y `tests/Unit/FrontendArchitectureTest.php`, `tests/Unit/DesignTokensOnlyTest.php`.
- [ ] **Step 3:** commits `Primeros pasos: se agrega driver.js 1.10.2` (solo `package.json` y lockfile) y `Primeros pasos: el recorrido con spotlight`.

### Task 9: Botón, panel, bienvenida y cierre

**Files:**
- Create: `components/first-steps/{first-steps-dock,first-steps-launcher,first-steps-panel,first-step-item,first-steps-welcome,first-steps-done}.tsx`
- Modify: `layouts/app/app-sidebar-layout.tsx` (`<FirstStepsDock />` antes de `<HumiDock />`), `components/user-menu-content.tsx`

**Interfaces:**
- Consumes: prop `firstSteps`, `impersonator`, `@/routes/tenant/first-steps` (Wayfinder: `welcome`, `hide`, `show`), `lib/first-steps/*`, `useFirstStepsTour()`.
- Produces: `FirstStepsDock` — no dibuja nada si `firstSteps` es `null`. Guarda si el popover está abierto, escucha `OPEN_FIRST_STEPS` y monta `FirstStepsTour`.

Comportamiento exacto:
- **Bienvenida** (`Dialog` de `components/ui`): abierta si `!welcomed && !hidden && impersonator === null` (Review Focus 3). Título «Te acompañamos en tus primeros pasos», cuerpo `welcomeText(total)`, botones **Empezar** y **Ahora no**. Los dos (y cerrar con Esc o fuera) hacen `router.post(welcome.url(slug), {}, { preserveScroll: true, preserveState: true })`; Empezar además abre el popover.
- **Lanzador:** oculto si `hidden`. `PopoverTrigger` `button` con `aria-label="Primeros pasos: 2 de 5 pasos"`, píldora `h-12` con un aro SVG de 28 px (`stroke-primary` sobre `stroke-border`, `aria-hidden`) y el texto `2 de 5`. Clases de posición: `fixed right-[4.75rem] bottom-20 z-40 md:bottom-4 print:hidden` más `group-has-[aside[aria-label=Humi]]/sidebar-wrapper:right-[436px]` (con Humi acoplado a la derecha, queda a su izquierda en vez de taparlo). Mismo borde, `bg-card`, `shadow-float` y foco que `humi-launcher.tsx`.
- **Panel:** `PopoverContent side="top" align="end"` de `w-[min(22rem,calc(100vw-2rem))]`: encabezado «Primeros pasos» + `progressText`, lista `<ol>` de `FirstStepItem` (palomita `Check` en círculo `bg-primary` si está hecho, número en círculo `border` si no; título, línea y botón **Guíame** `size="sm" variant="outline"` solo en los pendientes), pie «También tienes:» con un `Link` por `others` (si hay) y el botón de texto **Ocultar primeros pasos** (`router.post(hide.url(slug))`).
- **Guíame:** cierra el popover y llama `start(step)`.
- **Cierre:** si todos los pasos están hechos, el popover se abre solo y muestra `FirstStepsDone` en lugar de la lista: `IconTile` con `PartyPopper` (no el componente `Humi`: es de `components/assistant`), «Listo: Humetria ya está trabajando para ti» y un botón **Cerrar**. En la siguiente navegación la prop ya es `null`.
- **Menú de usuario:** `DropdownMenuItem` «Primeros pasos» (icono `ListChecks`) antes de «Configuración», solo si `firstSteps !== null`. Al tocarlo: si `hidden`, `router.post(show.url(slug), {}, { onSuccess: openFirstSteps })`; si no, `openFirstSteps()`.

- [ ] **Step 1:** escribir los componentes (cada uno < 100 líneas; `first-steps-dock` reparte en los demás).
- [ ] **Step 2:** `npm run check && npm run types:check && npm run build`; `tests/Unit/FrontendArchitectureTest.php`, `DesignTokensOnlyTest.php`, `DesignSystemReuseTest.php` → PASS.
- [ ] **Step 3:** revisar en `http://localhost` (con `FIRST_STEPS_ENABLED=true`) en claro, oscuro y 375 px contra V2-Panel y el lanzador de Humi: el botón no tapa a Humi ni la barra inferior; el popover cabe en 375 px.
- [ ] **Step 4:** commits `Primeros pasos: botón flotante con avance y panel de pasos`, `Primeros pasos: bienvenida y cierre`, `Primeros pasos: entrada en el menú de usuario`.

### Task 10: Anclas `data-tour` en cada pantalla

**Files (Modify):**
- `components/tenancy/create-work-center-dialog.tsx` (botón que abre el diálogo): `data-tour="add-work-center"`; `components/tenancy/work-centers-grid.tsx` (contenedor): `data-tour="work-centers-list"`
- `pages/organization/employees.tsx`: el `Button` de «Importar» (línea ~86) `data-tour="import-employees"`; el de agregar persona `data-tour="add-employee"`
- `components/campaigns/campaign-actions.tsx`: `Button` «Nueva evaluación» `data-tour="new-evaluation"`
- `pages/tenancy/onboarding.tsx`: contenedor de las preguntas `data-tour="applicability-questions"` y montar `<FirstStepsTour />` (la página usa `SetupFrame`: sin botón, con spotlight)
- `pages/attendance/setup.tsx`: la sección `#avance` `data-tour="attendance-setup"`
- `pages/tenancy/members.tsx`: `Button` «Invitar» `data-tour="invite-member"`
- `components/assistant/humi-launcher.tsx`: el `button` `data-tour="humi-launcher"`
- Test: `tests/Unit/FirstStepsAnchorsTest.php`

- [ ] **Step 1: test que falla**

```php
it('has in the pages every control a tour points at', function (): void {
    // lee los `target: '…'` de resources/js/lib/first-steps/steps.ts y afirma que cada uno aparece como
    // data-tour="…" en algún .tsx de resources/js; y que ningún data-tour del código falta en steps.ts
});
```

- [ ] **Step 2:** FAIL (faltan los nueve). **Step 3:** poner los atributos (con `asChild`, en el elemento hijo que sí se dibuja). **Step 4:** PASS; `npm run check`.
- [ ] **Step 5:** commit `Primeros pasos: cada pantalla marca el control que señala su recorrido`.

### Task 11: La tarjeta «Siguiente paso» no repite el paso 3

**Files:** Modify `components/tenancy/dashboard-next-step.tsx`.

**Decisión:** mientras los primeros pasos estén visibles y su paso `evaluation` esté pendiente, si la tarjeta iba a recomendar lo mismo (`repeatsEvaluationStep(firstSteps, unanswered > 0 && setsUp ? 'questions' : (step?.norm ?? null))`), se dibuja la misma `NextStepCard` con: título `stepCopy(evaluation).title`, meta «En tus primeros pasos», texto «Es tu siguiente paso. Síguelo desde el botón Primeros pasos, abajo a la derecha.» y el botón **Ver mis pasos** (`onClick={openFirstSteps}`). En cualquier otro caso (prop `null`, ocultos, paso 3 hecho, otra norma) la tarjeta queda exactamente como hoy.

- [ ] **Step 1:** la lógica ya está probada en Task 7 (`dashboard.test.ts`); aquí solo se usa. Mantener el componente bajo 100 líneas: si se pasa, la rama nueva va a `components/tenancy/dashboard-first-step.tsx`.
- [ ] **Step 2:** `npm run check && npm run types:check`; `php artisan test --compact tests/Feature/Tenancy/TenantDashboardTest.php` → PASS sin cambios. El caso de navegador va en Task 13.
- [ ] **Step 3:** commit `Panel: el siguiente paso remite a los primeros pasos cuando recomendaría lo mismo`.

### Task 12: Humi: paso 5 y herramienta `GetFirstSteps`

**Files:**
- Create: `Assistant/Tools/GetFirstSteps.php`; test `tests/Feature/Assistant/FirstStepsToolTest.php` (`mutates(GetFirstSteps::class)`)
- Modify: `AssistantServiceProvider` (lista de `ToolRegistry`, sin módulos: toda empresa la tiene), `resources/ai/humi.system.md`, `Assistant/README.md`, `resources/js/lib/assistant/tool-steps.ts` (`GetFirstSteps: 'tus primeros pasos'`) y su test, `tests/Feature/Assistant/ToolRegistryTest.php`
- Modify (paso 5): `lib/assistant/open-humi.ts`, `hooks/use-humi-open-request.ts`, `components/assistant/{humi-dock,humi-panel,humi-composer}.tsx`, `hooks/use-first-steps-tour.ts`; test `tests/js/lib/assistant/open-humi.test.ts`

**Interfaces:**
- Consumes: `FirstSteps::forUser($scope->userId)` (ya responde `null` a quien no es owner), `ScopeGuard`, `PanelLinks::dashboard()`.
- Produces:
  - `GetFirstSteps implements Tool` — `__construct(AssistantScope $scope, FirstSteps $firstSteps, PanelLinks $links, ScopeGuard $guard)`; `schema()` regresa `[]`; `description()`: «Los primeros pasos de la empresa para su owner: cuáles ya hizo, cuál sigue y el enlace a su pantalla. No recibe argumentos.» `handle()`: `guard->check`, luego `ToolCard('list', 'Primeros pasos', rows, $links->dashboard(), note)` con una fila por paso `['kind' => 'first_step', 'title' => …, 'detail' => 'Hecho'|'Pendiente', 'due' => null, 'link' => $step->href ?? $links->dashboard()]`; títulos = los de la tabla de Task 7 (constante en la herramienta). Con `null`: sin filas y `note` «No tienes primeros pasos pendientes.»
  - `openHumi(draft?: string): void` — `CustomEvent<{ draft: string | null }>`; `useHumiOpenRequest(open: (draft: string | null) => void)`; `HumiDock` guarda el borrador y lo pasa a `HumiPanel` → `HumiComposer` como `initialText` (`key` del compositor = el borrador, para que se escriba aunque Humi ya esté abierto). No se envía sola: la envía el owner.
  - Recorrido de `humi`: con `onPopoverRender` se agrega a la burbuja un `button` creado con `document.createElement` (texto `humiQuestion(modules)`, `textContent`, nunca `innerHTML`) que destruye el recorrido y llama `openHumi(pregunta)`.
- `humi.system.md`, una línea en la lista de lo que hace: «Si te preguntan qué sigue o cómo empezar, consulta los primeros pasos y contesta con el paso pendiente y su enlace.»

- [ ] **Step 1: tests que fallan**

```php
it('gives the owner each step with its state and its link', /* assistantScopeIn($a, TenantRole::Owner), interruptor prendido: 5 filas, 'Pendiente', links que empiezan con /admin/{slug}/ */);
it('gives nobody else any step', /* Hr y Viewer: rows === [] y la nota */);
it('never carries a name, an email or a figure of a person', /* empleado e invitado creados; el JSON no los contiene */);
it('refuses a scope of another company', /* scope de A con workIn($b) → ScopeMismatch */);
it('takes no company, tenant or person in its schema');   // lo recorre ToolRegistryTest
```

`ToolRegistryTest`: el caso «gives the five tools of Humi» pasa a seis, con `GetFirstSteps::class` al final (**cambio de comportamiento pedido por el spec**: Humi gana una herramienta; se renombra a «the six tools»). Ningún otro test existente cambia.

```ts
it('opens Humi with a draft', () => { /* escucha OPEN_HUMI; openHumi('¿Qué sigue?') → detail.draft === '¿Qué sigue?'; openHumi() → null */ });
```

- [ ] **Step 2:** FAIL. **Step 3:** implementar. **Step 4:** `php artisan test --compact tests/Feature/Assistant` y `npm run test:js` → PASS; Pint, PHPStan, `npm run check`, `npm run types:check`.
- [ ] **Step 5:** commits `Humi: la herramienta GetFirstSteps le dice al owner qué paso sigue` y `Primeros pasos: el paso de Humi abre el panel con una pregunta escrita`.

### Task 13: Navegador, revisión visual y cierre

**Files:** Create `tests/Browser/FirstStepsTest.php` (`beforeEach`: `config(['first_steps.enabled' => true])`; `signInToPanel()` da un owner en `/admin/acme`).

- [ ] **Step 1: tests** (cada uno termina con `->assertNoJavaScriptErrors()->assertScript(cspViolations(), '[]')`)

```php
it('welcomes a new owner, opens the steps and guides them to load their people', function (bool $dark, bool $mobile): void {
    // visitPage('dashboard', '/admin/acme', $dark, $mobile) → ve «Te acompañamos en tus primeros pasos»
    // click «Empezar» → ve «Primeros pasos» y «0 de 5 pasos»
    // click «Guíame» de «Carga a tu gente» → URL /admin/acme/employees, ve «Importa tu plantilla»
    // y document.querySelector('[data-tour="import-employees"]').classList.contains('driver-active-element')
    // Escape → la burbuja desaparece; first_steps_states tiene welcomed_at
})->with(/* claro/oscuro × escritorio/móvil, como MembersTest */);
it('leaves only the button after «Ahora no»');                       // sin diálogo al recargar; el botón «Primeros pasos: 0 de 5 pasos» sigue
it('hides the steps and takes them back from the user menu');        // Ocultar → toast y sin botón; menú de usuario → «Primeros pasos» → vuelve el panel
it('shows a centered bubble when the control is not on the page');   // Review Focus 4: sessionStorage con 'team' y visitar /admin/acme (sin data-tour="invite-member") → se ve «Invita a tu equipo», sin .driver-active-element
it('does not open the welcome to a superadmin working as the owner'); // Review Focus 3
it('points the panel card at the button instead of repeating the third step');   // con un centro: la tarjeta dice «En tus primeros pasos» y «Ver mis pasos» abre el panel
it('says it is done once, then the button is gone');                 // fila welcomed + todo hecho → «Listo: Humetria ya está trabajando para ti»; al recargar, sin botón
it('shows nothing to hr');
it('opens Humi with the example question written');                  // Guíame de «Conoce a Humi» → click en la pregunta → el campo de Humi la tiene
```

axe corre dentro de `visitPage()`; con el popover abierto se afirma además `aria-expanded="true"` en el botón.

- [ ] **Step 2:** `npm run build && composer test:browser -- --filter=FirstSteps` → PASS.
- [ ] **Step 3:** revisión visual en claro, oscuro y 375 px de: bienvenida, botón junto a Humi (cerrado, Humi acoplado, Humi en hoja), panel, burbuja de cada paso, cierre, tarjeta del panel. Contra V2-Panel, V3-Onboarding y el lanzador de Humi.
- [ ] **Step 4:** `composer ci:check` → verde.
- [ ] **Step 5:** commit `Primeros pasos: pruebas de navegador del recorrido completo`. No se abre el PR hasta que Victor lo pida.

---

## Preguntas abiertas (no bloquean)

- `driver.js` quedó en `1.10.2` por antigüedad; subir a `1.12.x` cuando tenga un par de semanas es un cambio de dependencia aparte.
- Con `FIRST_STEPS_ENABLED=false` en pruebas, ninguna otra suite ejerce la prop; si Victor prefiere prenderlo en todo el entorno de pruebas, hay que actualizar a mano los conteos de consultas de 57 archivos y cerrar la bienvenida en los tests de navegador.
