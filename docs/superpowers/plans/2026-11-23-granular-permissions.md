# Permisos granulares por empresa: plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el owner de cada empresa cree roles propios, use los 5 actuales como plantillas y dé permisos sueltos a una persona, desde un panel.

**Architecture:** Los roles propios son filas de `roles` con `tenant_id`; los permisos extra de una persona son filas de `model_has_permissions` con `tenant_id` (Spatie teams, ya soportado). Un catálogo en PHP describe cada permiso (módulo, etiqueta, sensibilidad, condiciones). Toda ruta `tenant.*` exige un permiso por middleware y `TenantRole` queda solo como plantillas.

**Tech Stack:** Laravel, spatie/laravel-permission (teams), Inertia + React, Pest. Sin dependencias nuevas.

**Spec:** `docs/superpowers/specs/2026-11-23-granular-permissions-design.md` (aprobado por Victor el 2026-11-23).

## Global Constraints

- TDD estricto: primero el test, verlo fallar por la razón correcta; el test no se afloja. Un commit = un comportamiento, cada uno en verde. Commits sin atribución a Claude. Sin push ni PR hasta que Victor lo pida.
- Mientras se trabaja solo los tests de la tarea (`php artisan test --compact --filter=…`), Pint y PHPStan (level max, sin supresiones); TS strictest, sin `as`, `!`, `any`.
- Código, rutas, tablas, columnas en inglés; UI en español es-MX, el panel tutea.
- Solo `Contracts`, `Events` y `Data` son públicos entre módulos; clases concretas `final`; el panel de roles vive en `Tenancy`.
- Toda lista paginada y buscada en servidor; N+1 prohibido (`expectsDatabaseQueryCount` en roles y miembros).
- Cada modelo con tenant prueba que la empresa A no lee ni modifica a B (404, no 403).
- Todo cambio de estado escribe log (`modulo.accion` en pasado) y los sensibles `RecordAudit`; nunca respuestas ni personas ligadas a respuestas.
- Mínimo por grupo, owner único por empresa (índices existentes) y anonimato no se tocan.
- Humi al corriente y `docs/` en el mismo PR. Toda página Inertia nueva: prueba de humo `visitPage()`. Un solo PR.
- Revisión visual en claro, oscuro y 375 px antes de cerrar.

## Review Focus

- Un rol propio llamado «owner»/«hr»: no debe volverse owner ni chocar con la plantilla (Task 2).
- Permiso dado en la empresa A visto desde B por URL o consulta: nada (Task 3).
- Rol propio con un solo permiso que abre una ruta olvidada: 403 en toda `tenant.*` (Task 4).
- Persona sin ningún permiso de lectura: aterriza en «Aún no tienes acceso», no en 500 ni redirección en bucle (Task 4).
- Borrar un rol con personas o con invitaciones abiertas: se rechaza con motivo (Task 2).
- Una union recibe un rol con permisos de cumplimiento: se rechaza (Task 5).

---

## File Structure

- `app/Modules/Tenancy/Support/PermissionDefinition.php`, `PermissionCatalog.php`: el catálogo.
- `app/Modules/Tenancy/Support/CompanyRoles.php`: se amplía (roles propios, permisos directos, efectivos).
- `app/Modules/Tenancy/Actions/{CreateCompanyRole,UpdateCompanyRole,DeleteCompanyRole,GrantMemberPermissions}.php`.
- `app/Modules/Tenancy/Http/Middleware/RequireRoutePermission.php` reemplaza a `LimitRestrictedRoles`.
- `app/Modules/Tenancy/Http/Controllers/RoleController.php` + Requests; `MemberController` se ajusta.
- `app/Modules/Tenancy/Events/MemberPermissionsChanged.php`.
- `resources/js/pages/tenancy/roles.tsx`, `role-editor.tsx`, `no-access.tsx`; `components/tenancy/permission-toggles.tsx`.
- Migración nueva: columnas `label`, `based_on` en `roles` y permisos de cobertura.

---

### Task 1: Catálogo de permisos y migración de cobertura

**Files:**
- Create: `app/Modules/Tenancy/Support/PermissionDefinition.php`, `app/Modules/Tenancy/Support/PermissionCatalog.php`
- Create: `database/migrations/2026_11_23_100000_add_role_labels_and_coverage_permissions.php`
- Test: `tests/Feature/Tenancy/PermissionCatalogTest.php`

**Interfaces:**
- Produces: `PermissionDefinition(string $name, string $label, string $module, string $description, bool $sensitive = false, bool $ownerOnly = false, ?Module $requiresModule = null, bool $unionAllowed = false)` (`final readonly`).
- Produces: `PermissionCatalog::all(): list<PermissionDefinition>`, `::find(string $name): ?PermissionDefinition`, `::assignable(Tenant $tenant): list<PermissionDefinition>` (sin `ownerOnly`, filtrado por tipo de cuenta), `::unavailableReason(Tenant $tenant, PermissionDefinition $p): ?string` (módulo no contratado → texto es-MX).
- Produces: permisos nuevos `view-dashboard`, `view-work-centers`, `view-nom035-route`; `roles.label` (nullable string), `roles.based_on` (nullable string).

- [ ] **Step 1: Write the failing tests** in `PermissionCatalogTest.php`: `every permission row is in the catalog and vice versa` (compara `permissions.name` con `PermissionCatalog::all()`), `catalog marks manage-members and manage-tenants-like permissions ownerOnly and excludes them from assignable()`, `a union only gets compensation permissions`, `sensitive set is exactly [view-trauma-screenings, handle-complaints, manage-complaint-channel, view-compensation, manage-compensation, review-workplaces]` (lista decidida aquí; Victor la revisa), `unavailableReason is set when the module is not contracted`.
- [ ] **Step 2: Run** `php artisan test --compact --filter=PermissionCatalogTest`. Expected: FAIL, clase no existe.
- [ ] **Step 3: Implement** los dos Support y la migración. Catálogo con los 26 permisos de módulo sembrados, más `manage-organization`, `manage-members`, `view-trauma-screenings`, `use-assistant`, `manage-campaigns`, `manage-worker-access` y los 3 nuevos. La migración reparte los nuevos a owner/hr/viewer (los tres) y a committee según hoy ve (tablero y rutas NOM-037/Ley Silla: `view-dashboard`, `view-work-centers`); complaints solo `view-dashboard`. Sigue el patrón de `2026_12_20_100000_add_vacations_permissions.php` (`whereNull('tenant_id')`).
- [ ] **Step 4: Run** el mismo filtro. Expected: PASS.
- [ ] **Step 5: Commit** `Catálogo de permisos de la empresa`.

### Task 2: Roles propios de la empresa

**Files:**
- Modify: `app/Modules/Tenancy/Support/CompanyRoles.php`
- Create: `app/Modules/Tenancy/Actions/CreateCompanyRole.php`, `UpdateCompanyRole.php`, `DeleteCompanyRole.php`; `app/Modules/Tenancy/Exceptions/CompanyRoleInUse.php`, `CompanyRoleLimitReached.php`
- Modify: `config/tenancy.php` (nuevo `max_custom_roles` = 20; añadir a `.env.example` solo si se lee de env)
- Test: `tests/Feature/Tenancy/CompanyRolesTest.php`

**Interfaces:**
- Produces: `CreateCompanyRole::handle(Tenant $tenant, string $label, list<string> $permissions, ?TenantRole $basedOn, User $by): Spatie Role` (nombre interno `custom_<uuid>`); `UpdateCompanyRole::handle(Role $role, string $label, list<string> $permissions, User $by): void`; `DeleteCompanyRole::handle(Role $role, User $by): void` (lanza `CompanyRoleInUse` si hay personas o invitaciones abiertas).
- Produces: `CompanyRoles::customRoles(Tenant): Collection<int, Role>` y `CompanyRoles::roleOf(User, Tenant): ?Role` (plantilla o propio).

- [ ] **Step 1: Write the failing tests:** crea rol con etiqueta «owner» sin chocar con la plantilla y `hasRole('owner')` sigue falso; permisos que no están en `PermissionCatalog::assignable` se rechazan (`ownerOnly`, módulo no contratado); no se pasa de 20; editar cambia permisos y escribe log y bitácora; borrar con una persona asignada y con invitación abierta lanza `CompanyRoleInUse`; empresa B no ve ni edita roles de A (404 por URL en Task 7, aquí por consulta con `BelongsToCurrentTenant`-equivalente: `roles.tenant_id`); un permiso sensible marca `sensitive` en la bitácora, también al editar un rol con personas.
- [ ] **Step 2: Run** `--filter=CompanyRolesTest`. Expected: FAIL.
- [ ] **Step 3: Implement** las tres Actions con `DB::transaction()`, `RecordAudit` y `Log::info('tenant_role.created|updated|deleted', …)`; `based_on` y `label` se escriben en columnas de Task 1.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `El owner crea, edita y borra roles propios`.

### Task 3: Permisos directos por persona y permisos efectivos

**Files:**
- Modify: `app/Modules/Tenancy/Support/CompanyRoles.php`
- Create: `app/Modules/Tenancy/Actions/GrantMemberPermissions.php`, `app/Modules/Tenancy/Events/MemberPermissionsChanged.php`
- Modify: `app/Modules/Tenancy/Actions/RemoveMember.php`
- Test: `tests/Feature/Tenancy/MemberPermissionsTest.php`

**Interfaces:**
- Produces: `GrantMemberPermissions::handle(TenantMembership $member, list<string> $permissions, User $by): void` (sincroniza los directos de la persona en esa empresa).
- Produces: `CompanyRoles::directPermissionsOf(User, Tenant): list<string>`, `::effectivePermissions(User, Tenant): array<string, 'role'|'direct'>` (de dónde viene cada uno; ignora los de módulos no contratados).
- Produces: `MemberPermissionsChanged(string $tenantId, int $userId)`.

- [ ] **Step 1: Write the failing tests:** el permiso directo se suma al del rol y `can()` lo respeta dentro de la empresa; el mismo usuario en la empresa B no lo tiene (consulta y por URL); quitar a la persona de la empresa borra sus directos de esa empresa y no los de otras; un permiso de módulo no contratado no se evalúa como efectivo; `ownerOnly` se rechaza; cambiar permisos emite `MemberPermissionsChanged` y escribe log/bitácora.
- [ ] **Step 2: Run** `--filter=MemberPermissionsTest`. Expected: FAIL.
- [ ] **Step 3: Implement** con `inTeam()` existente de `CompanyRoles` y `syncPermissions`.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `Una persona puede tener permisos además de su rol`.

### Task 4: Toda ruta `tenant.*` exige un permiso

**Files:**
- Create: `app/Modules/Tenancy/Http/Middleware/RequireRoutePermission.php`, `app/Modules/Tenancy/Support/RoutePermissions.php` (mapa nombre de ruta → permiso, con comodines `tenant.vacations.*`)
- Modify: `routes/company.php` (sustituye `LimitRestrictedRoles` por el middleware), y los `routes/*.php` incluidos si conviene declarar el permiso junto a la ruta
- Modify: `app/Modules/Tenancy/Enums/TenantRole.php` (se elimina `allowedRoutes()`; `startRoute()` se mueve a Task 4b)
- Test: `tests/Feature/Tenancy/RoutePermissionCoverageTest.php`, `tests/Feature/Tenancy/TemplateAccessUnchangedTest.php`

**Interfaces:**
- Produces: `RoutePermissions::for(string $routeName): ?string`; `RequireRoutePermission::handle` responde 403 sin el permiso, y deja pasar las rutas listadas como excepción (cuenta personal, selector de empresa, cada una con razón).
- Consumes: `PermissionCatalog` y los permisos nuevos de Task 1.

- [ ] **Step 1: Write the failing tests:** `every tenant.* route has a permission or a documented exception` (recorre `Route::getRoutes()`, estilo `RouteSecurityTest`); `a role with only handle-complaints gets 403 on every other tenant.* route`; `TemplateAccessUnchangedTest`: para viewer, committee y complaints, rutas permitidas por el middleware nuevo == lista previa de `allowedRoutes()` copiada como constante en el test (viewer: todas las `view-*` y GET; committee: `tenant.telework.*`, `tenant.seating.*`, `tenant.complaints.*`; complaints: `tenant.complaints.*`; ambos más tablero); GET exige `view-*` y escribir `manage-*`.
- [ ] **Step 2: Run** `--filter=RoutePermissionCoverageTest` y `TemplateAccessUnchangedTest`. Expected: FAIL.
- [ ] **Step 3: Implement** el mapa por módulo, trabajando archivo de rutas por archivo (un commit por grupo de módulos, cada uno en verde). Las rutas con permiso ya definido en su FormRequest conservan ese `Gate` como segunda barrera. Páginas sin permiso hoy: tablero → `view-dashboard`; centros → `view-work-centers`; rutas/expediente NOM-035 → `view-nom035-route`; `plan` y `privacy` → `manage-members`.
- [ ] **Step 4: Run** ambos y `--filter=Tenancy`. Expected: PASS.
- [ ] **Step 5: Commit** por módulo; último commit: `Ninguna ruta de la empresa queda sin permiso`.

### Task 4b: A dónde llega quien tiene pocos permisos

**Files:**
- Modify: `app/Modules/Tenancy/Http/Controllers/HomeController.php`, `TenantDashboardController.php`
- Create: `resources/js/pages/tenancy/no-access.tsx`, ruta `tenant.no-access` (permiso: ninguno, exento con razón)
- Test: `tests/Feature/Tenancy/LandingAfterLoginTest.php`

**Interfaces:**
- Produces: `StartRoute::for(User, Tenant): string` (primera herramienta cuyo `view-*`/`manage-*` tiene la persona, en el orden del menú; si no hay, `tenant.no-access`).
- Consumes: `CompanyRoles::effectivePermissions`.

- [ ] **Step 1: Write the failing tests:** committee llega a telework; complaints a complaints; un rol propio con solo `view-vacations` llega a vacaciones; uno sin permisos llega a `no-access` (200, sin bucle, con a quién pedirlo: el owner por nombre); el tablero solo muestra tarjetas de lo permitido.
- [ ] **Step 2: Run** `--filter=LandingAfterLoginTest`. Expected: FAIL.
- [ ] **Step 3: Implement**; `TenantRole::startRoute()` se borra.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `Cada persona aterriza en lo que sí puede ver`.

### Task 5: Reglas que dependían del rol

**Files:**
- Modify: `app/Modules/Complaints/Listeners/EndAssignmentsOnRoleConflict.php`, `app/Modules/Complaints/Enums/RespondentKind.php`, `app/Modules/Tenancy/Events/MemberRoleChanged.php`, `app/Modules/Tenancy/Enums/TenantType.php`
- Test: `tests/Feature/Complaints/RoleConflictByPermissionTest.php` (junto al existente), `tests/Feature/Tenancy/AccountTypeRolesTest.php`

**Interfaces:**
- Produces: `MemberRoleChanged(string $tenantId, int $userId, string $role)` conserva su forma (`$role` pasa a ser el nombre interno del rol efectivo) y el listener también escucha `MemberPermissionsChanged`.
- Produces: `RespondentKind::excludedPermission(): ?string` (`Hr` → `manage-organization`; `Management` → `null`, y owner se decide por la plantilla owner).

- [ ] **Step 1: Write the failing tests:** una persona con rol propio + `manage-organization` directo termina sus asignaciones contra RH (`end_reason = role_conflict`); quien pasa a owner termina las de dirección; los tests existentes de C-45 siguen verdes sin cambios; una union rechaza un rol propio con permisos de cumplimiento y acepta uno de compensación; `TenantType::allows` por permisos.
- [ ] **Step 2: Run** los dos filtros. Expected: FAIL.
- [ ] **Step 3: Implement** `allows`/`assignable` sobre el catálogo (`unionAllowed`).
- [ ] **Step 4: Run** más `--filter=Complaints`. Expected: PASS.
- [ ] **Step 5: Commit** `El conflicto de quejas y el tipo de cuenta hablan de permisos`.

### Task 6: Invitaciones y miembros por rol

**Files:**
- Modify: `TenantInvitation` (`role` → `role_id` + `extra_permissions` json), migración (en la de Task 1 solo si no se mezcla comportamiento; si no, nueva), `InviteMember`, `InviteMemberRequest`, `MemberRoleRules`, `ChangeMemberRole`, `AcceptInvitation`, `InvitationEmail`, `MemberSummary` (`role` pasa a `CompanyRoleSummary`)
- Create: `app/Modules/Tenancy/Data/CompanyRoleSummary.php` (`string $key, string $label, bool $template`)
- Test: `tests/Feature/Tenancy/InviteWithCustomRoleTest.php` y los existentes de `MembersTest`, `AcceptInvitationTest` ajustados al nuevo contrato (el comportamiento no cambia)

**Interfaces:**
- Consumes: Task 2 y 3. Produces: invitar con `role` = clave de plantilla o de rol propio, más `permissions`; aceptar aplica rol y extras en una transacción; borrar un rol bloquea mientras haya invitaciones abiertas (ya cubierto en Task 2).

- [ ] **Step 1: Write the failing tests:** invitar con rol propio y extras; aceptar los aplica; el correo dice la etiqueta del rol; un rol de otra empresa se rechaza; cambiar el rol de una persona conserva sus extras.
- [ ] **Step 2: Run** `--filter=InviteWithCustomRoleTest`. Expected: FAIL.
- [ ] **Step 3: Implement**; `RoleMatrix` se genera del catálogo y de los roles reales (`RoleMatrix::for(Tenant)`), no de constantes.
- [ ] **Step 4: Run** más `--filter=Members` y `--filter=Invitation`. Expected: PASS.
- [ ] **Step 5: Commit** `Las invitaciones y los miembros usan roles propios`.

### Task 7: Pantallas de roles y miembros

**Files:**
- Create: `RoleController.php` (index, create, store, edit, update, destroy), `StoreRoleRequest.php`, `UpdateRoleRequest.php`, `routes/tenancy-roles` dentro de `routes/company.php`
- Create: `resources/js/pages/tenancy/roles.tsx`, `role-editor.tsx`; `resources/js/components/tenancy/permission-toggles.tsx`, `role-list.tsx`
- Modify: `resources/js/pages/tenancy/members.tsx`, `invite-member.tsx`; `ForbidWhileImpersonating::protectedRoutes()` (+ `tenant.roles.*`)
- Test: `tests/Feature/Tenancy/RolesPageTest.php`, `tests/Browser/Tenancy/RolesSmokeTest.php`, el flujo crítico `tests/Browser/Tenancy/CustomRoleFlowTest.php`

**Interfaces:**
- Consumes: Tasks 1 a 6. Props del editor: grupos por módulo `{module, permissions: [{name, label, description, sensitive, enabled, unavailableReason|null, source: 'role'|'direct'|null}]}`.

- [ ] **Step 1: Write the failing tests:** solo el owner entra (hr 403, otro tenant 404, soporte ve de solo lectura, quien suplanta 404); lista paginada con conteo de personas y sin N+1 (`expectsDatabaseQueryCount`); props sin respuestas individuales; guardar un sensible requiere `acknowledge_sensitive=true`; los apagados por plan no se pueden activar; flujo de browser: crear rol, asignarlo, la persona entra y ve solo lo suyo; `visitPage()` de roles, editor y no-access.
- [ ] **Step 2: Run** `--filter=RolesPageTest`. Expected: FAIL.
- [ ] **Step 3: Implement** partiendo de `members.tsx` (marco con migas, tarjetas, tablas `muted`, estado vacío con siguiente paso); un componente por archivo, ≤300 líneas, lógica no visual en `hooks/`/`lib/`; revisar en claro, oscuro y 375 px.
- [ ] **Step 4: Run** PHP y `composer test:browser -- --filter=Roles` (requiere `npm run build`). Expected: PASS.
- [ ] **Step 5: Commit** `Panel de roles y permisos de la empresa`.

### Task 8: Humi, documentación y cierre

**Files:**
- Modify: lector del módulo en `app/Modules/Assistant/Support/Readers/` (conteo de roles propios y qué puede hacer quien pregunta), `docs/normas/…` donde aplique, README del módulo Tenancy si existe, `docs/operations` si cambia algo operativo
- Test: `tests/Feature/Assistant/ModuleCoverageTest.php` (sigue verde), prueba del lector

- [ ] **Step 1: Write the failing test** del lector (conteos y estados, sin personas ni respuestas).
- [ ] **Step 2: Run** `--filter=Assistant`. Expected: FAIL.
- [ ] **Step 3: Implement** el lector y actualizar docs; revisar si `tests/Unit/CiCodeChangedTest.php` exige registrar algún documento.
- [ ] **Step 4: Run** Pint `--test`, PHPStan, `npm run check`, `types:check` y la suite completa local antes de cualquier push (regla de Victor).
- [ ] **Step 5: Commit** `Humi conoce los roles propios`.

---

## Self-Review

- **Cobertura del spec:** catálogo (T1), roles propios y nombres internos (T2), permisos directos y efectivos (T3), cobertura de rutas, plantillas sin pérdida y aterrizaje (T4, T4b), conflicto de quejas, tipo de cuenta y owner (T5), invitaciones (T6), pantallas, soporte, suplantación, bitácora y advertencia (T7), Humi y docs (T8). Demo seeder: sin cambios, a propósito.
- **Tipos:** `CompanyRoleSummary`, `MemberPermissionsChanged`, `PermissionCatalog::assignable` se definen antes de usarse.
- **Riesgo principal:** T4 es lo más grande (retocar rutas por módulo); va en commits por grupo de módulos.
- **Pendiente de Victor:** las 4 decisiones del final del spec, y la lista de sensibles de T1.
