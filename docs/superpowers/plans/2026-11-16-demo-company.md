# Empresa demo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Una empresa demo (Manufacturas Alba del Bajío) que la consola prepara y reinicia, con una historia coherente en todos los módulos, una sala de demostración y una puerta pública para que el prospecto participe desde su teléfono.

**Architecture:** `tenants.is_demo` marca la empresa y la saca de cobros, métricas y correo. La historia vive en `database/seeders/Demo/` (fuera de `app/Modules`), una clase por módulo, usando solo acciones reales y datos escritos a mano (sin Faker ni factories: producción no los tiene). El módulo `Demos` solo ve el contrato `DemoWorkspace`. La sala es una página de la consola; la puerta es una ruta pública con sesión que vence.

**Tech Stack:** Laravel 13 / PHP, Inertia + React + TypeScript, Pest, Postgres, colas (Horizon).

**Spec:** `docs/superpowers/specs/2026-11-16-demo-company-design.md`

## Global Constraints

- TDD estricto; PHPStan `level: max` y TS strictest sin supresiones; sin dependencias nuevas (Faker **no** se mueve a `require`).
- Código, rutas, tablas e identificadores en inglés; textos de UI en español (es-MX); el panel tutea.
- Fechas en UTC (`timestampTz`); relativas a hoy; se muestran en `America/Mexico_City`.
- Otro módulo no importa modelos, acciones ni enums ajenos; la historia usa las **acciones** de cada módulo desde `database/seeders/Demo/`, que no es módulo.
- Anonimato: respuestas sin llave a empleado, invitación ni token; mínimo de 5 por grupo; nada de la puerta se liga a una respuesta.
- Correo de la demo solo a `@demo.humetria.test`, y nunca sale.
- Empresa: nombre «Manufacturas Alba del Bajío, S.A. de C.V.»; centros de 15 (Sucursal Monterrey, Guía I), 30 (Oficinas Querétaro, I y II), 51 (Centro de distribución, I y III) y 84 (Planta Querétaro, I y III con muestra) = 180 personas.
- Una sola empresa demo (índice único parcial). Sesión de la puerta: 2 horas por omisión; grupo reservado de ~40 trabajadores.
- Una ruta pública nueva se agrega a `publicRouteNames()` en `tests/Feature/RouteSecurityTest.php` con su razón.
- Pruebas acotadas mientras se trabaja (`php artisan test --compact --filter=…`); la suite completa es el CI. Un PR para todo el trabajo.

## Review Focus

- Preparar dos veces seguidas sin reiniciar: no duplica nada (idempotente) y no rompe el candado.
- Dos clics simultáneos en «Preparar»/«Reiniciar»: solo corre uno.
- Un prospecto con la encuesta abierta mientras se reinicia: al enviar ve «enlace ya no disponible», no un 500.
- Preparar cerca de medianoche (UTC vs. México): las fechas «hoy», «ayer» y «hace 6 meses» no se corren un día.
- Un visitante que recarga la puerta o llega con el cookie de antes del reinicio: conserva su trabajador, o recibe uno nuevo, nunca dos.

---

### Task 1: La marca de empresa demo

**Files:**
- Create: `database/migrations/2026_11_16_000001_add_is_demo_to_tenants_table.php`, `app/Modules/Tenancy/Contracts/DemoCompanies.php`, `app/Modules/Tenancy/Support/DemoCompanyRegistry.php`
- Modify: `app/Modules/Tenancy/Models/Tenant.php`, `app/Modules/Tenancy/Providers/*` (enlazar el contrato), `database/factories/Tenancy/TenantFactory.php` (estado `demo()`), `tests/Feature/TenantScopedModelsTest.php` solo si hace falta
- Test: `tests/Feature/Tenancy/DemoCompanyTest.php`

**Interfaces:**
- Produces: `interface DemoCompanies { public function isDemo(string $tenantId): bool; public function id(): ?string; }`; `Tenant::$is_demo` (bool, fuera de `#[Fillable]`); `TenantFactory::demo(): static`.

- [ ] **Step 1: Test** `it('lets only one demo company exist')` (segunda fila `is_demo = true` lanza `QueryException`), `it('is not mass assignable')` (`Tenant::create([... 'is_demo' => true])` queda en `false`), `it('says which company is the demo')` (`isDemo($demo->id)` true, otra false; `id()` null sin demo).
- [ ] **Step 2:** `php artisan test --compact --filter=DemoCompanyTest` → FAIL (columna inexistente).
- [ ] **Step 3:** Migración `boolean is_demo default false not null` + índice único parcial `where is_demo` (sobre una constante). Implementar el contrato con una consulta; enlazarlo.
- [ ] **Step 4:** Mismo comando → PASS; `vendor/bin/phpstan analyse` y `vendor/bin/pint --test` sobre lo cambiado.
- [ ] **Step 5:** Commit `La empresa demo: marca is_demo y un contrato para saber cuál es`.

### Task 2: La demo no cuenta en cobros ni métricas, y la impersonación no la bloquea

**Files:**
- Modify: `app/Modules/Billing/Actions/RenewSubscriptions.php`, `app/Modules/Tenancy/Actions/ListTenants.php` (resumen y conteos), `app/Modules/Tenancy/Support/PlatformOverview.php`, `app/Modules/Tenancy/Support/RealUsage.php`, `app/Modules/Compensation/Http/Middleware/BlockWhileImpersonating.php`, `app/Modules/Assistant/Http/Middleware/AllowAssistant.php`, `app/Modules/Tenancy/Http/Controllers/ImpersonateOwnerController.php`
- Test: `tests/Feature/Tenancy/DemoCompanyExclusionsTest.php`, ajustes en las pruebas existentes de esos middleware

**Interfaces:**
- Consumes: `DemoCompanies::isDemo()` (Task 1).
- Produces: `POST admin/companies/{tenant}/impersonate` acepta `as` (`owner` | `hr`, por omisión `owner`); `hr` solo si la empresa es la demo, y entra como el primer miembro con rol `hr` que no sea staff (404 si no hay).

- [ ] **Step 1: Tests:** `it('does not renew or bill the demo')` (una suscripción de la demo no genera estado de cuenta); `it('leaves the demo out of the company counts and revenue')`; `it('lets the superadmin see Compensation and Humi while impersonating the demo')` (200) y `it('still blocks them in every other company')` (404, ya existe: no se toca); `it('enters the demo as HR')` y `it('refuses as=hr in a real company')` (404 o 422 según el FormRequest).
- [ ] **Step 2:** Correr → FAIL.
- [ ] **Step 3:** Cada exclusión con `DemoCompanies::isDemo($tenantId)` en un solo punto por pieza; los dos middleware dejan pasar si la empresa actual es la demo; el controlador valida `as` en un FormRequest cuyo `authorize()` exige `view-console`.
- [ ] **Step 4:** Correr → PASS; Pint y PHPStan.
- [ ] **Step 5:** Commit `La demo queda fuera de cobros y métricas, y se puede ver completa al entrar desde la consola`.

### Task 3: Ningún correo de la demo sale

**Files:**
- Create: `app/Listeners/BlockDemoMail.php` (kernel; escucha `Illuminate\Mail\Events\MessageSending`)
- Modify: `app/Providers/AppServiceProvider.php`; las acciones programadas que recorren las empresas (ubicarlas con `grep -rn "CompanyRegistry" app/Modules/*/Actions` y saltar la demo en el punto común, si lo hay; si no, en cada `Send*Digests`/`Remind*`)
- Test: `tests/Feature/Demo/BlockDemoMailTest.php`

**Interfaces:**
- Produces: `const string DemoMail::DOMAIN = 'demo.humetria.test'` (en `App\Support\DemoMail`) y `BlockDemoMail::handle(MessageSending): bool` (retorna `false` para cancelar).

- [ ] **Step 1: Tests:** `it('cancels a message whose recipients are all demo addresses')` y loguea `demo.mail_blocked` con solo el conteo; `it('lets through a message with any real recipient')`; `it('skips the demo in the digests and reminders')`.
- [ ] **Step 2:** Correr → FAIL. **Step 3:** Implementar el listener (devolver `false` cancela el envío) y el salto. **Step 4:** PASS. **Step 5:** Commit `Los correos de la demo se bloquean y la demo no recibe resúmenes ni recordatorios`.

### Task 4: Marca «DEMO» en los reportes

**Files:** modificar el layout compartido de los PDF y las páginas de resultados/informes (ubicarlos con `grep -rln "pdf" resources/views app/Modules/*/Support | head`; una sola marca en el layout común, no una por reporte). Test: `tests/Feature/Demo/DemoWatermarkTest.php`.

- [ ] **Step 1: Test:** `it('marks every report of the demo company as DEMO')` (el HTML del reporte de la demo contiene «DEMO»; el de otra empresa no) y `it('shows no mark in another company')`.
- [ ] **Step 2–5:** FAIL → implementar leyendo `DemoCompanies` → PASS → commit `Los reportes de la demo llevan la marca DEMO`.

### Task 5: Cimientos de la historia: reloj, personas deterministas y la plantilla

**Files:**
- Create: `database/seeders/Demo/DemoClock.php`, `database/seeders/Demo/DemoRoster.php`, `database/seeders/Demo/DemoWorkspaceRunner.php` (implementa `DemoWorkspace`), `app/Modules/Demos/Contracts/DemoWorkspace.php`, `database/seeders/Demo/Stories/OrganizationStory.php`
- Test: `tests/Feature/Demo/DemoFoundationTest.php`

**Interfaces:**
- Produces: `interface DemoWorkspace { public function prepare(): void; public function reset(): void; }`; `DemoClock::at(string $relative, Closure $callback): mixed` (corre `$callback` con `Carbon::setTestNow` a «hoy − N días» a las 09:00 de `America/Mexico_City`, y siempre restaura); `DemoRoster::people(): list<DemoPerson>` con `DemoPerson{key, name, email, centerKey, area, position, level, reportsTo}` y `DemoRoster::employeeId(string $key): string` (UUID v5 estable); interfaz `DemoStory { public function tell(DemoContext $context): void; }` con el orden que fija el runner.

- [ ] **Step 1: Tests:** `it('builds exactly 180 people in the four centers')` (15, 30, 51, 84); `it('gives every person the same id on every run')`; `it('uses only demo addresses')`; `it('restores the clock even when the callback fails')`; `it('creates the company flagged as demo, active and with every module')`.
- [ ] **Step 2:** FAIL. **Step 3:** `OrganizationStory` crea empresa, centros, áreas, niveles, empleados (con id determinista) y líneas de reporte por las acciones de Tenancy y Organization; miembros dueño y RH con correo demo. El runner abre `Companies::enter()` para trabajar dentro de la empresa. **Step 4:** PASS. **Step 5:** Commit `Los cimientos de la demo: una plantilla de 180 personas y un reloj para contar la historia`.

### Task 6: NOM-035, resultados e intervención

**Files:** `database/seeders/Demo/Stories/Nom035Story.php`; test `tests/Feature/Demo/DemoNom035StoryTest.php`.

- [ ] **Step 1: Tests:** `it('closes a first evaluation six months ago with a high-risk result in the plant night shift')`; `it('applies Guía I to the 15 center, I and II to the 30, I and III to the 51 and 84')`; `it('shows no group below the minimum of 5')` (resultados por grupo); `it('has an intervention program with done, running, overdue and planned actions')`; `it('keeps answers keyless')` (ninguna tabla de respuestas con llave a empleado, invitación o token); `it('leaves an open campaign at about 60% with pending invitations reserved for the door')`.
- [ ] **Step 2–4:** FAIL → historia con `LaunchCampaign`, `SubmitSurvey`, `CloseCampaign` y las acciones de `Interventions`, dentro de `DemoClock::at()`; las respuestas se reparten para que el grupo «Producción turno 2» salga en riesgo alto con n ≥ 5 → PASS.
- [ ] **Step 5:** Commit `La demo cuenta una evaluación NOM-035 cerrada, su intervención y una campaña abierta`.

### Task 7: Clima y pulso, y las encuestas abiertas para el público

**Files:** `database/seeders/Demo/Stories/EngagementStory.php`; test `tests/Feature/Demo/DemoEngagementStoryTest.php`.

- [ ] **Step 1: Tests:** `it('has a closed climate survey and two closed pulses whose trend improves')`; `it('has one open pulse at about 60% with at least 40 pending invitations')`; `it('keeps every group at or above 5 respondents')`; `it('keeps answers keyless')`.
- [ ] **Step 2–5:** FAIL → implementar con las acciones de `Engagement` → PASS → commit `La demo tiene clima y pulso con tendencia y una encuesta abierta`.

### Task 8: Asistencia

**Files:** `database/seeders/Demo/Stories/AttendanceStory.php`; test `tests/Feature/Demo/DemoAttendanceStoryTest.php`.

- [ ] **Step 1: Tests:** `it('has zones, schedules and a policy for every center')`; `it('has six months of closed days with more overtime in the plant night shift')`; `it('has an activated kiosk in the Oficinas center')`; `it('has no enrolled devices and no passkeys')` (la asistencia en vivo la marca quien vincula su dispositivo); `it('has pending correction requests and a week awaiting confirmation')`.
- [ ] **Step 2–5:** FAIL → historial con `RecordMark`, `CloseDays`, `RequestCorrection`; el checador con `IssueKioskDevice`/`ActivateKioskDevice` → PASS → commit `La demo tiene meses de asistencia y un checador listo para vincular`.

### Task 9: Vacaciones y permisos

**Files:** `database/seeders/Demo/Stories/LeaveStory.php`; test `tests/Feature/Demo/DemoLeaveStoryTest.php`.

- [ ] **Step 1: Tests:** `it('has a policy, entitlements and balances for everyone')`; `it('has approved, rejected and pending requests, with pending ones routed to a manager')`; `it('has holidays for the year')`; `it('never gives a balance below zero')`.
- [ ] **Step 2–5:** FAIL → `GrantEntitlements`, `RouteRequests` y las acciones de solicitud → PASS → commit `La demo tiene vacaciones con saldos y solicitudes pendientes`.

### Task 10: Denuncias

**Files:** `database/seeders/Demo/Stories/ComplaintsStory.php`; test `tests/Feature/Demo/DemoComplaintsStoryTest.php`.

- [ ] **Step 1: Tests:** `it('has an active public channel with a stable access code')`; `it('has handlers designated')`; `it('has cases in several states: received, in review, closed')`; `it('keeps reporters anonymous')` (ninguna fila liga una queja con un empleado); `it('does not log or audit complaint text')`.
- [ ] **Step 2–5:** FAIL → `OpenChannel`, `DesignateHandler`, `SubmitComplaint`, `StartReview`, `CloseCase` → PASS → commit `La demo tiene un canal de denuncias activo con casos en distintos estados`.

### Task 11: Políticas, compensación, Ley Silla y teletrabajo

**Files:** `database/seeders/Demo/Stories/DisclosureStory.php`, `CompensationStory.php`, `SeatingStory.php`, `TeleworkStory.php`; tests `tests/Feature/Demo/Demo{Disclosure,Compensation,Seating,Telework}StoryTest.php`. Cuatro commits, uno por módulo.

- [ ] **Step 1: Tests (uno por módulo):** políticas publicadas con acuses cerca del 87 %; un escenario de contrato colectivo con su costo calculado; evaluación de Ley Silla en Oficinas con medidas abiertas y cerradas y su encuesta sin llaves; 12 teletrabajadores con lista de verificación, documentos y comité.
- [ ] **Step 2–5:** FAIL → historia con las acciones de cada módulo → PASS → commits `La demo publica políticas con acuses`, `La demo trae un escenario de contrato colectivo`, `La demo evalúa Ley Silla en las oficinas`, `La demo tiene teletrabajo NOM-037`.

### Task 12: Los números cuadran entre módulos

**Files:** Test `tests/Feature/Demo/DemoCoherenceTest.php`; sin código nuevo salvo correcciones a las historias.

- [ ] **Step 1: Tests:** `it('has the same 180 people in the org chart, the centers, attendance, leave and the campaigns')`; `it('has answers plus pending equal to invitations in every campaign and survey')`; `it('reaches every page the owner sees without a query exception')` (recorre las rutas del panel de la demo con `get()` y espera 200); `it('keeps every Inertia prop free of individual answers')`; `it('uses at most N queries on campaign progress and results')` (`expectsDatabaseQueryCount`).
- [ ] **Step 2–5:** FAIL por las incoherencias reales → corregir la historia que desvíe, nunca el test → PASS → commit `Los números de la demo cuadran entre módulos`.

### Task 13: Preparar y reiniciar, con candado y progreso

**Files:**
- Create: `app/Modules/Demos/Jobs/PrepareDemo.php`, `app/Modules/Demos/Jobs/ResetDemo.php`, `app/Modules/Demos/Support/DemoRun.php`, migración `demo_runs` (`id`, `kind`, `status`, `step`, `started_at`, `finished_at`, `error_class`)
- Modify: `database/seeders/Demo/DemoWorkspaceRunner.php` (`reset()`), `app/Modules/Demos/Providers/DemosServiceProvider.php`
- Test: `tests/Feature/Demo/DemoRunTest.php`

**Interfaces:**
- Consumes: `DemoWorkspace` (Task 5).
- Produces: `DemoRun::start(string $kind): ?DemoRun` (null si ya hay una corriendo; candado atómico), `DemoRun::step(string $label): void`, `DemoRun::finish(): void`, `DemoRun::current(): ?DemoRun`.

- [ ] **Step 1: Tests:** `it('prepares an empty install into the full story')`; `it('is idempotent: preparing twice leaves one company and the same counts')`; `it('refuses a second run while one is in progress')`; `it('keeps the company row, the slug, the complaint code, the kiosk and every portal link across a reset')`; `it('leaves no row of the demo tenant in any tenant table except audit_entries after a reset')` (recorre las tablas con `tenant_id`); `it('records progress and failure without the exception message')`; `it('sends no email')`.
- [ ] **Step 2–5:** FAIL → `reset()` borra el contenido en una transacción, en el orden inverso a las historias, y vuelve a contarla; los trabajadores con ids deterministas conservan su `PortalLink` → PASS → commit `Preparar y reiniciar la demo, con un candado y su progreso`.

### Task 14: La sala de demostración en la consola

**Files:**
- Create: `app/Modules/Demos/Http/Controllers/DemoRoomController.php`, `app/Modules/Demos/Http/Requests/DemoRoomRequest.php`, `resources/js/pages/demos/room.tsx`, `resources/js/components/demos/room-*.tsx` (estado, enlaces, QR, sesión de la puerta)
- Modify: `routes/demos.php`, la navegación de la consola, `tests/Unit/BrowserCoverageTest.php` (solo si exige registrar la página)
- Test: `tests/Feature/Demos/DemoRoomTest.php`, `tests/Browser/DemoRoomTest.php`

**Interfaces:**
- Produces: rutas `GET admin/demo` (`demo-room.show`), `POST admin/demo/prepare` (`demo-room.prepare`), `POST admin/demo/reset` (`demo-room.reset`), `POST admin/demo/door` y `DELETE admin/demo/door` (`demo-room.door.open|close`); todas `auth`, `verified` y `Gate::authorize('view-console')` en el `authorize()` del FormRequest.

- [ ] **Step 1: Tests:** 403 antes de validar a quien no es staff; la sala muestra estado, antigüedad, trabajadores libres, enlaces del canal de denuncias y del checador (QR), y los botones de entrar como dueño y como RH; preparar y reiniciar encolan el job y responden con el estado; las props no traen respuestas individuales; browser: `visitPage()` en claro, oscuro y 375 px.
- [ ] **Step 2–5:** FAIL → controlador delgado, FormRequest, página con las piezas de la consola más parecida (tarjetas, estado vacío con siguiente paso) → PASS → commit `La sala de demostración en la consola`.

### Task 15: La puerta

**Files:**
- Create: migración `demo_door_sessions` (`id`, `code_hash`, `expires_at`, `closed_at`) y `demo_door_assignments` (`session_id`, `employee_id`, `claimed_at`), `app/Modules/Demos/Models/DoorSession.php`, `app/Modules/Demos/Actions/{OpenDoor,CloseDoor,AdmitVisitor,ReleaseAbandoned}.php`, `app/Modules/Demos/Http/Controllers/DoorController.php`, `resources/js/pages/demos/door-full.tsx`
- Modify: `routes/demos.php`, `tests/Feature/RouteSecurityTest.php` (`demo.door` en `publicRouteNames()`), `app/Http/IndexablePages` no (siempre `noindex`), el sembrado (Task 7 y 6) para reservar ~40 trabajadores libres con invitaciones pendientes
- Test: `tests/Feature/Demos/DoorTest.php`, `tests/Browser/DoorFlowTest.php`

**Interfaces:**
- Consumes: `PortalAccess` del portal (emitir el enlace de un empleado; localizarlo con `grep -rn "class PortalAccess" app`), `DemoCompanies`.
- Produces: `OpenDoor::handle(int $minutes = 120): array{url: string}` (el código solo se muestra al abrir; se guarda su hash); `AdmitVisitor::handle(string $code, ?string $signedCookie): AdmissionResult` (`Admitted{employeeId, portalUrl}` | `Full` | `Closed`); `ReleaseAbandoned::handle(): int` (libera a quien no entró hace 30 min y emite invitaciones nuevas).

- [ ] **Step 1: Tests:** `it('sends a visitor to the portal of a free demo worker')`; `it('gives a returning visitor the same worker')` y `it('gives only one worker per visitor')`; `it('says the room is full when no worker is free')`; `it('refuses a closed, expired or unknown code with the same page')`; `it('only ever hands out workers of the demo company')`; `it('throttles by address')`; `it('reissues fresh invitations when releasing abandoned workers')` y que el avance cuente solo invitaciones vigentes; `it('links no visitor to any answer')`; browser: QR → portal → contesta la encuesta en 375 px.
- [ ] **Step 2–5:** FAIL → implementar con cookie firmada, `lockForUpdate` al reclamar → PASS → commit `La puerta: un QR que da a cada persona del público su portal demo`.

### Task 16: Runbook y cierre

**Files:** Create `docs/operations/demo.md` (cómo preparar, reiniciar, abrir la puerta, qué enseñar y en qué orden; qué hacer si falla); Modify `docs/testing.md` si cambia el reparto de shards, y `scripts/ci-code-changed.sh` + `tests/Unit/CiCodeChangedTest.php` si `docs/operations` ya los obliga (confirmar con ese test).

- [ ] **Step 1:** Escribir el runbook. **Step 2:** `php artisan test --compact --filter=CiCodeChangedTest` → PASS. **Step 3:** Pint, PHPStan, `npm run check` y `types:check` sobre lo cambiado. **Step 4:** Commit `Runbook de la demo`.

---

## Self-Review

- Cobertura del spec: marca (T1), exclusiones y entrada (T2), correo (T3), marca en reportes (T4), historia y números (T5–T12), preparar/reiniciar y candado (T13), sala (T14), puerta, seguridad y «liberar» (T15), operación (T16).
- Tipos consistentes: `DemoCompanies` (T1) lo usan T2–T4 y T15; `DemoWorkspace` (T5) lo usa T13; `DemoRun` (T13) lo usa T14.
- Pendiente de confirmar al construir: el punto común de las acciones programadas (T3) y el layout común de los PDF (T4); ambos se ubican con el `grep` indicado.
