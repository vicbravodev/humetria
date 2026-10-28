# Invitar al owner (Tenancy) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** El superadmin invita al owner de una empresa desde `/admin/companies`. El owner acepta por un enlace de un solo
uso, que vence a los 7 días, y entra. Su primer acceso inicia la prueba de 14 días si la empresa sigue "Sin iniciar".

**Architecture:** Dos tablas nuevas en el módulo Tenancy:
- `tenant_invitations`: el token se guarda como hash SHA-256 y el estado se calcula.
- `tenant_memberships`: quién pertenece a qué empresa y con qué rol. Por ahora el único rol es `owner`.

La consola suma una columna "Owner" y tres acciones: invitar, reenviar y cancelar. Una página pública,
`tenancy/accept-invitation`, resuelve el enlace en todos sus casos y acepta dentro de una transacción. Antes de la
feature va un PR de harness aparte que manda el correo local a mailtrap-local.

**Tech Stack:**
- Laravel 13 con Notifications y Fortify
- Postgres, con índices únicos parciales
- Pest 5: Feature, Browser y `mutates()`
- Inertia v3 + React, Wayfinder

**Spec:** `docs/superpowers/specs/2026-10-28-tenancy-invite-owner-design.md`

## Global Constraints

**Reglas de `CLAUDE.md`:**
- TDD estricto: ver el RED antes de escribir el código.
- Larastan `max` y TS strictest, sin supresiones.
- Clases `final` y `declare(strict_types=1)`.
- Sin dependencias nuevas.

**Módulo:**
- `Models`, `Actions`, `Enums`, `Notifications` y `Http` de Tenancy no se usan fuera del módulo ni de
  `Database\Factories\Tenancy`.
- Ni `Actions` ni `Models` usan `Illuminate\Http`, `Inertia`, `request()`, `session()` ni `auth()`.

**Textos:**
- Identificadores en inglés.
- UI y correo en español (es-MX), tuteando. Ningún texto visible en inglés (`visitPage` lo revisa).

**Fechas:**
- Se guardan en UTC (`timestampTz`).
- Se muestran en `config('app.display_timezone')` con el formato de la consola: `4 nov 2026`, sin el punto de Carbon.

**Constantes:**
- La invitación vence a los **7 días**: `TenantInvitation::VALID_DAYS = 7`.
- La prueba sigue en `Tenant::TRIAL_DAYS = 14`.

**Token:** `Str::random(64)`. Solo vive en el correo y en la URL. En la base se guarda
`token_hash = hash('sha256', $token)`, que es `unique`.

**Logs y bitácora:**
- Llevan solo ids: `tenant_id`, `invitation_id` y `user_id`.
- **Nunca** llevan correo, nombre ni token.
- `email` se agrega a `RedactSensitiveData`.

**Commits:**
- Uno por tarea, con `composer test` en verde al cerrar cada una. Si la tarea tocó frontend, también
  `npm run test:js` y `npm run types:check`.
- Mensajes en inglés y en imperativo, como los de `git log`, sin atribución a Claude.
- El push siempre pide permiso.

## Review Focus

1. **Dos clics en "Aceptar"** (doble envío o dos pestañas): solo uno crea la cuenta y la membresía. El segundo ve
   "ya no es válido" y no da un 500 por llave duplicada. La acción bloquea la invitación con `lockForUpdate()` y vuelve a
   revisar que siga pendiente → test en la Tarea 3.
2. **Correo con mayúsculas o espacios** (" Ana@Acme.MX "): se guarda en minúsculas y sin espacios. Al aceptar se
   encuentra la cuenta existente de `ana@acme.mx` y no se crea una segunda → tests en las Tareas 2 y 4.
3. **Abrir el enlace justo al vencer:** a `expires_at` exacto la invitación ya está vencida (`now < expires_at`) → test
   en la Tarea 1.
4. **Reenviar después de vencida:** el enlace nuevo funciona y el viejo no → test en la Tarea 5.
5. **Aceptar en una empresa ya activa o desactivada:** entra como owner, pero `trial_started_at` sigue nulo → test en
   la Tarea 3.

---

### Task 0 (PR aparte): el correo local va a mailtrap-local

**Branch:** `harness/mailtrap-local` desde `origin/main`. Va antes que la feature, en su propio PR, con la plantilla de
`.github/pull_request_template.md`.

**Files:**
- Modify: `.env.example`, con `MAIL_MAILER=smtp`, `MAIL_HOST=mailtrap-local` y `MAIL_PORT=3535`. Se deja
  `MAIL_FROM_ADDRESS`.
- Modify: `CLAUDE.md` → "Entorno local". La línea del correo dice: "El correo local va a mailtrap-local (bandeja en
  `http://localhost:3550`): ningún correo, SMS ni WhatsApp sale del entorno de desarrollo."

- [ ] **Step 1:** Confirmar que `phpunit.xml` fija `MAIL_MAILER=array`. Ya lo hace, en la línea 34. Así los tests no
  dependen del `.env`.
- [ ] **Step 2:** Hacer los cambios de los dos archivos.
- [ ] **Step 3:** Correr `composer test`. Expected: verde.
- [ ] **Step 4:** Commit `Send local mail to mailtrap-local so invitations can be seen in a browser`.

Al terminar, avisar al usuario que cambie esas tres variables en su `.env` y reinicie el contenedor con
`./vendor/bin/sail up -d`.

---

**Branch de la feature:** `tenancy/invite-owner`, desde `origin/main` una vez mergeado el PR de la Tarea 0. Sobre ella
ya está el spec, sin commitear: el primer commit lo incluye.

### Task 1: Membresía e invitaciones: tablas, modelos y estado

**Files:**
- Create: `database/migrations/2026_10_28_100000_create_tenant_memberships_table.php`
- Create: `database/migrations/2026_10_28_100100_create_tenant_invitations_table.php`
- Create: `app/Modules/Tenancy/Enums/TenantRole.php`
- Create: `app/Modules/Tenancy/Enums/InvitationStatus.php`
- Create: `app/Modules/Tenancy/Models/TenantMembership.php`
- Create: `app/Modules/Tenancy/Models/TenantInvitation.php`
- Create: `database/factories/Tenancy/TenantMembershipFactory.php`
- Create: `database/factories/Tenancy/TenantInvitationFactory.php`
- Modify: `app/Modules/Tenancy/Models/Tenant.php` (relaciones)
- Test: `tests/Feature/Tenancy/InvitationStatusTest.php`
- Test: `tests/Feature/Tenancy/MembershipStorageTest.php`

**Interfaces:**
- Produces:
  - `enum TenantRole: string { case Owner = 'owner'; }`
  - `enum InvitationStatus: string`, con `Pending`, `Expired`, `Accepted` y `Cancelled` (valores en snake_case).
    - `label(): string` devuelve "Invitación enviada", "Vencida", "Aceptada" y "Cancelada".
  - Tabla `tenant_memberships` con estas columnas:
    - `id` (bigint)
    - `tenant_id` (uuid, FK `cascadeOnDelete`)
    - `user_id` (FK `cascadeOnDelete`)
    - `role` (string, `check (role in ('owner'))`)
    - `timestamps`

    Restricciones:
    - `unique(tenant_id, user_id)`
    - Índice único parcial `tenant_memberships_one_owner` sobre `tenant_id` `where role = 'owner'` (con
      `DB::statement`)
  - Tabla `tenant_invitations` con estas columnas:
    - `id` (uuid)
    - `tenant_id` (FK)
    - `role` (con el mismo `check`)
    - `name`
    - `email`
    - `token_hash` (`unique`)
    - `expires_at`
    - `accepted_at` y `cancelled_at`, nullable
    - `invited_by` (FK a `users`, `nullOnDelete`, nullable)
    - `timestamps`

    Las fechas son `timestampTz(precision: 0)`, como en `tenants`. Restricción: índice único parcial
    `tenant_invitations_one_open` sobre `(tenant_id, role)` `where accepted_at is null and cancelled_at is null`.
  - `TenantMembership`:
    - `#[Fillable(['tenant_id','user_id','role'])]`, cast `role` a `TenantRole`
    - `user(): BelongsTo<User>`
    - `tenant(): BelongsTo<Tenant>`
  - `TenantInvitation`:
    - `HasVersion4Uuids` y `public const int VALID_DAYS = 7;`
    - Casts: `role` a `TenantRole`; las tres fechas `immutable_datetime`.
    - `status(): InvitationStatus`, con prioridad: `accepted_at` → Accepted; `cancelled_at` → Cancelled;
      `now() < expires_at` → Pending; si no, Expired.
    - `static hashToken(string $token): string`
    - `scopeOpen(Builder $query)`: `accepted_at` y `cancelled_at` nulos.
    - `tenant(): BelongsTo<Tenant>`
  - `Tenant`:
    - `ownerMembership(): HasOne<TenantMembership>`, con `where role = owner`
    - `openInvitation(): HasOne<TenantInvitation>`, con `->open()`
    - `invitations(): HasMany<TenantInvitation>`, que usa el `scopeBindings()` de la Tarea 5
  - Factories:
    - `TenantMembershipFactory`: owner por defecto.
    - `TenantInvitationFactory`: owner, `expires_at = now + 7 días` y `token_hash` de un token aleatorio. Estados:
      - `withToken(string $token)`
      - `expired()`
      - `accepted()`
      - `cancelled()`

- [ ] **Step 1: Escribir los tests que fallan**

`InvitationStatusTest` lleva `mutates(TenantInvitation::class)` y congela el tiempo con `travelTo`:
- `it('is pending until the exact expiry instant')`:
  - con `expires_at = now()->addSecond()` → `Pending`
  - a `expires_at` exacto → `Expired`
  - un segundo después → `Expired`
- `it('reports accepted and cancelled before looking at the expiry')`: aceptada con fecha vencida → `Accepted`; cancelada
  sin vencer → `Cancelled`.
- `it('hashes the token with sha256')`: `TenantInvitation::hashToken('abc')` es igual a `hash('sha256', 'abc')`.

`MembershipStorageTest`:
- `it('allows a single owner per company')`: crear un segundo owner en la misma empresa lanza `UniqueConstraintViolationException`.
- `it('allows a single open invitation per company and role')`: una abierta y otra abierta lanzan; una cancelada y una
  abierta pasan.
- `it('rejects an unknown role')`: un `DB::table(...)->insert` con `role = 'boss'` lanza `QueryException`.
- `it('finds the owner and the open invitation of a company')`: las relaciones devuelven lo esperado.

- [ ] **Step 2:** Correr `php artisan test --compact tests/Feature/Tenancy/InvitationStatusTest.php tests/Feature/Tenancy/MembershipStorageTest.php`.
  Expected: FAIL, porque las clases no existen.
- [ ] **Step 3:** Implementar las migraciones, los enums, los modelos, las factories y las relaciones de `Tenant` según
  las Interfaces.
- [ ] **Step 4:** Correr los mismos tests. Expected: PASS. Correr `composer test`. Expected: verde.
- [ ] **Step 5:** Commit `Store company memberships and owner invitations` (incluye el spec y este plan).

---

### Task 2: Invitar al owner desde la consola

**Files:**
- Create: `app/Modules/Tenancy/Data/OwnerInvitationData.php`, con `string $name` y `string $email`. El email ya va en
  minúsculas y sin espacios.
- Create: `app/Modules/Tenancy/Http/Requests/StoreInvitationRequest.php`
- Create: `app/Modules/Tenancy/Actions/InviteOwner.php`
- Create: `app/Modules/Tenancy/Notifications/OwnerInvitation.php`
- Create: `app/Modules/Tenancy/Http/Controllers/InvitationConsoleController.php`
- Modify:
  - `routes/tenancy.php`
  - `app/Modules/Tenancy/Actions/ListTenants.php`
  - `app/Modules/Tenancy/Http/Controllers/TenantConsoleController.php` (props `owner` e `invitation`)
  - `app/Logging/RedactSensitiveData.php` (llave `email`)
  - `lang/es.json` (textos de la plantilla de notificaciones)
  - `lang/es/validation.php` → `attributes` (`name` y `email`, si faltan)
- Create (front):
  - `resources/js/components/tenancy/tenant-owner-cell.tsx`
  - `resources/js/components/tenancy/invite-owner-dialog.tsx`
- Modify (front):
  - `resources/js/components/tenancy/tenant-row.tsx` y `tenants-table.tsx` (columna "Owner")
  - `resources/js/types/tenancy.ts`
- Test:
  - `tests/Feature/Tenancy/InviteOwnerTest.php`
  - `tests/Feature/Tenancy/TenantConsoleTest.php` (props y número de consultas)
  - `tests/Unit/Logging/RedactSensitiveDataTest.php`, o el archivo donde hoy se prueba la redacción
  - `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Consumes: los modelos y enums de la Tarea 1.
- Produces:
  - Ruta `companies.invitations.store`: `POST admin/companies/{tenant}/invitations`, con `auth` y `verified`.
  - `InviteOwner::handle(Tenant $tenant, OwnerInvitationData $data, User $invitedBy): TenantInvitation`. Dentro de
    `DB::transaction`:
    1. Cancela la invitación abierta de la empresa (`cancelled_at = now`).
    2. Crea la nueva, con `expires_at = now + VALID_DAYS`.
    3. Escribe el log `tenant_invitation.sent` y la bitácora (sujeto: la invitación; metadata `tenant_id`).
    4. Envía `OwnerInvitation` a `Notification::route('mail', $email)`, con `afterCommit()`.

    El token en claro solo pasa de la acción a la notificación.
  - `OwnerInvitation` recibe en el constructor el nombre comercial, el nombre de la persona, el token y `expires_at`.
    `toMail()` arma un `MailMessage`:
    - Asunto: "Te invitaron a Humetría como owner de {trade_name}"
    - Saludo: "Hola, {name}"
    - Botón "Aceptar invitación", que lleva a `route('invitations.show', $token)`
    - Línea: "El enlace vence el {d MMM YYYY}."
    - Línea: "Si no esperabas este correo, ignóralo."

    En esta tarea, la URL usa la ruta `invitations.show`, que nace en la Tarea 3. Hasta entonces se arma con
    `url('admin/invitations/'.$token)`; la Tarea 3 la cambia a `route()`.
  - `StoreInvitationRequest`:
    - autoriza con `Gate::allows('manage-tenants')`
    - `name`: requerido, string, máx. 255
    - `email`: requerido, `email`, máx. 255, normalizado en `prepareForValidation`
    - en un hook `after()`: si el correo es el del owner actual, error en `email` con "Esta persona ya es el owner."
    - `toData(): OwnerInvitationData`
  - Props de la página, en cada fila de `tenants`:
    - `owner: {name: string, email: string} | null`
    - `invitation: {id: string, name: string, email: string, status: 'pending'|'expired', status_label: string, expires_on: string} | null`
  - `ListTenants` hace eager load de `ownerMembership.user` y `openInvitation`.

- [ ] **Step 1: Escribir los tests que fallan**

`InviteOwnerTest` (`Notification::fake()`):
- `it('lets the superadmin invite the owner of a company')`:
  - redirige a `companies.index` con el toast "Invitación enviada."
  - la base tiene la invitación con `email` en minúsculas y sin espacios (envía `' Ana@Acme.MX '`), `token_hash` de
    64 hex y `expires_at` = now + 7 días
  - `Notification::assertSentOnDemand(OwnerInvitation::class)`, dirigida a `ana@acme.mx`, con una URL que contiene un
    token cuyo hash es `token_hash`
- `it('cancels the open invitation when inviting again')`: también cuando la anterior está vencida.
- `it('rejects an invalid email, an empty name and the current owner')`: errores en `email` y `name`, sin invitación nueva.
- `it('logs and audits the invitation without the email, the name or the token')`: con `Log::spy()`, el contexto de
  `tenant_invitation.sent` y la `metadata` de la fila de `audit_entries` no contienen el correo, el nombre ni el token.
  El `tenant_id` de la bitácora es nulo.
- `it('answers 404 to anyone who is not the superadmin')` y `it('sends guests to the login')`.

`TenantConsoleTest`:
- `it('shows each company owner and open invitation')`: props `owner` e `invitation` como en las Interfaces, con
  `expires_on` en el formato `4 nov 2026`.
- `it('lists companies with a fixed number of queries')`: `expectsDatabaseQueryCount(n)` con 3 empresas, una con owner y
  otra con invitación. `n` no crece si hay más filas.

Redacción:
- `it('redacts email addresses')`: un contexto `['email' => 'a@b.mx']` sale redactado.

Browser (`tests/Browser/TenantConsoleTest.php`):
- `it('invites the owner from the console')`: abrir el Dialog "Invitar owner", llenar y enviar. La fila muestra
  "Invitación enviada" y el correo.
- Mantener `visitPage('tenancy/index', …)` en claro, oscuro y móvil, con una fila de cada caso de la columna Owner.

- [ ] **Step 2:** Correr `php artisan test --compact tests/Feature/Tenancy/InviteOwnerTest.php tests/Feature/Tenancy/TenantConsoleTest.php`.
  Expected: FAIL, porque la ruta no existe y las props faltan.
- [ ] **Step 3:** Implementar el backend según las Interfaces y `php artisan wayfinder:generate --with-form`.
- [ ] **Step 4:** Implementar el front:
  - `TenantOwnerCell` muestra los casos de la tabla "Consola" del spec. En esta tarea: "Sin invitar", owner con *Activo*
    e invitación pendiente o vencida.
  - El botón "Invitar owner" abre `InviteOwnerDialog`, que usa `<Form>` con nombre y correo.
  - Con owner, el botón dice "Cambiar owner" y abre el mismo Dialog.
- [ ] **Step 5:** Correr los tests de Feature y `composer test:browser --filter=TenantConsole`. Expected: PASS. Después
  correr `composer test`, `npm run test:js` y `npm run types:check`. Expected: verde.
- [ ] **Step 6:** Commit `Let the superadmin invite a company owner by email`.

---

### Task 3: Aceptar con una cuenta nueva (inicia la prueba)

**Files:**
- Create: `app/Modules/Tenancy/Data/NewAccountData.php`, con `string $name` y `string $password`. La contraseña va en
  claro y el modelo la hashea por su cast.
- Create: `app/Modules/Tenancy/Actions/AcceptInvitation.php`
- Create: `app/Modules/Tenancy/Http/Requests/AcceptInvitationRequest.php`
- Create: `app/Modules/Tenancy/Http/Controllers/AcceptInvitationController.php`
- Create: `resources/js/pages/tenancy/accept-invitation.tsx`
- Create: `resources/js/components/tenancy/accept-invitation-form.tsx`
- Create: `resources/js/components/tenancy/invitation-notice.tsx`
- Modify:
  - `routes/tenancy.php`
  - `app/Modules/Tenancy/Notifications/OwnerInvitation.php` (cambia a `route('invitations.show', …)`)
  - `app/Modules/Tenancy/Http/Requests/TenantRules.php` (agrega `invitations` e `invitation` a `RESERVED_SLUGS`)
  - `tests/Feature/RouteSecurityTest.php` (lista pública)
- Test:
  - `tests/Feature/Tenancy/AcceptInvitationTest.php`
  - `tests/Feature/Tenancy/TrialStartTest.php`
  - `tests/Browser/AcceptInvitationTest.php`

**Interfaces:**
- Consumes: la Tarea 1, y la Tarea 2 para el flujo de Browser.
- Produces:
  - Rutas públicas, con `throttle:10,1`:
    - `invitations.show`: `GET admin/invitations/{token}`
    - `invitations.accept`: `POST admin/invitations/{token}`

    En `publicRouteNames()`: `'invitations.show', // guest: invitation link from the email; the token is the credential`
    y `'invitations.accept', // guest: accepts the invitation; throttled, single-use token`.
  - `AcceptInvitation::handle(TenantInvitation $invitation, User|NewAccountData $accepter): User`. Dentro de
    `DB::transaction`:
    1. Vuelve a leer la invitación con `lockForUpdate()`. Si ya no está `Pending`, lanza `InvitationNoLongerValid`, una
       excepción del módulo en `app/Modules/Tenancy/Exceptions/`.
    2. Con un `NewAccountData`, crea el `User` con el `email` de la invitación y `email_verified_at = now`.
    3. Hace un `updateOrCreate` de la membresía `owner`.
    4. Pone `accepted_at = now`.
    5. Si `$tenant->status() === TenantStatus::NotStarted`, pone `trial_started_at = now`.
    6. Escribe los logs y la bitácora `tenant_invitation.accepted` y, si aplica, `tenant.trial_started`. Lo hace dentro de
       `Context::scope(fn () => …, ['user_id' => $user->id, 'tenant_id' => $tenant->id])`, para que `RecordAudit` tome
       a quien acepta como actor y a la empresa como tenant.

    No inicia sesión: eso lo hace el controlador.
  - `AcceptInvitationController`:
    - `show(string $token)`: busca la invitación con `hashToken`. Renderiza `tenancy/accept-invitation` con estas props:
      - `state`, con los valores `'invalid' | 'new_account' | 'sign_in' | 'confirm' | 'other_account'`
      - `trade_name`, `email` y `name`; son nulos cuando `state` es `invalid`
      - `token`

      En esta tarea solo existen `invalid` y `new_account`; la Tarea 4 agrega el resto.
    - `accept(AcceptInvitationRequest, string $token)`: si no está pendiente, redirige a `show`, que muestra `invalid`.
      Si está pendiente, llama a la acción, hace `Auth::login($user)`, regenera la sesión y redirige a `dashboard` con
      el toast "Bienvenido a Humetría.".
  - `AcceptInvitationRequest`: `name` es requerido, string y de máximo 255. `password` usa `passwordRules()` del concern
    `App\Concerns\PasswordValidationRules`.

- [ ] **Step 1: Escribir los tests que fallan**

`AcceptInvitationTest`:
- `it('shows the form for a valid link')`: `state = new_account`, con nombre comercial, correo y nombre.
- `it('shows the link as invalid when it is unknown, used, cancelled or expired')`: un dataset de cuatro casos, todos con
  `state = invalid` y sin `trade_name` ni `email`.
- `it('creates the owner account, signs it in and lands on the dashboard')`:
  - el usuario existe con `email_verified_at`
  - la contraseña sirve con `Hash::check`
  - hay membresía owner y `accepted_at` tiene valor
  - el usuario quedó autenticado
  - redirige a `/admin`
- `it('rejects a weak or unconfirmed password and creates nothing')`.
- `it('accepts a link only once')`: un segundo POST con el mismo token no crea nada y termina en `state = invalid`.
- `it('records the acceptance with the new owner as actor')`: la fila de bitácora `tenant_invitation.accepted` tiene
  `actor_id` del nuevo usuario y `tenant_id` de la empresa. Ni log ni bitácora contienen el correo, el nombre ni el token.
- `it('rate limits the link')`: el undécimo intento en un minuto da 429.

`TrialStartTest` (`mutates(AcceptInvitation::class)`):
- `it('starts the trial on the first acceptance of a company that has not started')`: `trial_started_at = now`.
- `it('leaves the dates alone when the company is active, deactivated, in trial or past its trial')`: un dataset de
  cuatro estados, con las tres fechas sin cambio.

`RouteSecurityTest` y `ReservedSlugsTest` siguen en verde con las entradas nuevas.

Browser (`tests/Browser/AcceptInvitationTest.php`):
- `visitPage('tenancy/accept-invitation', …)` en `new_account` y en `invalid`, en claro, oscuro y móvil.
- `it('accepts an invitation and lands on the dashboard')`: crear la invitación con la factory y `withToken`, abrir el
  enlace sin sesión, llenar y enviar. Termina en `/admin`.

- [ ] **Step 2:** Correr `php artisan test --compact tests/Feature/Tenancy/AcceptInvitationTest.php tests/Feature/Tenancy/TrialStartTest.php`.
  Expected: FAIL, porque las rutas no existen.
- [ ] **Step 3:** Implementar las rutas, la acción, la excepción, el request y el controlador. Agregar los slugs
  reservados y la lista pública.
- [ ] **Step 4:** Implementar la página con `AuthLayout`, como `auth/login`:
  - Título: "Acepta tu invitación".
  - Descripción: "Te invitaron como owner de {trade_name}".
  - `AcceptInvitationForm`: correo de solo lectura, nombre, contraseña y confirmación.
  - `InvitationNotice` para `invalid`: "Este enlace ya no es válido. Pide uno nuevo al administrador de Humetría."
- [ ] **Step 5:** Correr los tests de Feature y `composer test:browser --filter=AcceptInvitation`. Expected: PASS.
  Después correr `composer test`, `npm run test:js` y `npm run types:check`. Expected: verde.
- [ ] **Step 6:** Commit `Let an invited owner create an account and start the company trial`.

---

### Task 4: Aceptar con una cuenta existente

**Files:**
- Modify:
  - `app/Modules/Tenancy/Http/Controllers/AcceptInvitationController.php`
  - `routes/tenancy.php`
  - `resources/js/pages/tenancy/accept-invitation.tsx`
  - `resources/js/components/tenancy/invitation-notice.tsx`
- Test:
  - `tests/Feature/Tenancy/AcceptInvitationExistingAccountTest.php`
  - `tests/Browser/AcceptInvitationTest.php`

**Interfaces:**
- Consumes: `AcceptInvitation::handle(…, User $accepter)` de la Tarea 3.
- Produces:
  - `show` decide `state` así:
    - sin sesión, si existe un `User` con el correo invitado → `sign_in`, y guarda `url.intended` con el enlace
    - con sesión y el mismo correo → `confirm`
    - con sesión y otro correo → `other_account`, exista o no una cuenta para el correo invitado
  - `accept`:
    - con sesión y el mismo correo: acepta con ese `User`, sin request de contraseña
    - con sesión y otro correo: 403, sin cambios
    - sin sesión y con una cuenta existente para el correo: redirige a `login`, sin cambios
  - Ruta `invitations.switch-account`: `POST admin/invitations/{token}/switch-account`, con `auth`. Hace
    `Auth::guard('web')->logout()`, invalida y regenera la sesión y redirige a `invitations.show`.
  - UI:
    - `sign_in`: texto "Ya tienes una cuenta en Humetría. Inicia sesión para unirte a {trade_name}.", con un botón que va
      a `login()`.
    - `confirm`: "Únete a {trade_name}", con un botón "Aceptar invitación" que hace POST a `invitations.accept`.
    - `other_account`: "Esta invitación es para {email}. Cierra sesión para continuar.", con un botón "Cerrar sesión y
      continuar".

- [ ] **Step 1: Escribir los tests que fallan**
- `it('asks an existing account to sign in and returns to the link afterwards')`: `show` da `state = sign_in`. Después,
  POST a `login.store` con esas credenciales redirige al enlace.
- `it('adds a signed-in existing account as owner without touching its password or name')`: el hash de la contraseña,
  el nombre y `email_verified_at` no cambian. Hay membresía owner.
- `it('finds the existing account whatever the case of the invited email')`: la cuenta `ana@acme.mx` con una invitación
  creada como `Ana@Acme.MX` (normalizada en la Tarea 2) da `sign_in` y no crea un segundo usuario.
- `it('refuses to accept for a different signed-in account')`: `show` da `other_account` y el POST da 403, tanto si el
  correo invitado tiene cuenta como si no. El caso de un superadmin con sesión queda cubierto.
- `it('switches account and comes back to the link')`: el POST a `switch-account` cierra la sesión y redirige al enlace.
- `it('never offers the password form for an email that already has an account')`: con `state = sign_in`, un POST con
  `name` y `password` no cambia la contraseña y redirige al login.

Browser: `visitPage` en `sign_in`, `confirm` y `other_account`.

- [ ] **Step 2:** Correr `php artisan test --compact tests/Feature/Tenancy/AcceptInvitationExistingAccountTest.php`.
  Expected: FAIL.
- [ ] **Step 3:** Implementar `show`, `accept`, `switchAccount` y los estados de la página.
- [ ] **Step 4:** Correr esos tests y el Browser. Expected: PASS. Después correr `composer test`, `npm run test:js` y
  `npm run types:check`. Expected: verde.
- [ ] **Step 5:** Commit `Let an existing account accept an owner invitation after signing in`.

---

### Task 5: Reenviar y cancelar

**Files:**
- Create: `app/Modules/Tenancy/Actions/ResendInvitation.php`
- Create: `app/Modules/Tenancy/Actions/CancelInvitation.php`
- Modify:
  - `app/Modules/Tenancy/Http/Controllers/InvitationConsoleController.php`
  - `routes/tenancy.php`
  - `resources/js/components/tenancy/tenant-owner-cell.tsx`
- Create: `resources/js/components/tenancy/cancel-invitation-dialog.tsx`
- Test:
  - `tests/Feature/Tenancy/ResendCancelInvitationTest.php`
  - `tests/Browser/TenantConsoleTest.php`

**Interfaces:**
- Produces:
  - `companies.invitations.resend`: `POST admin/companies/{tenant}/invitations/{invitation}/resend`
  - `companies.invitations.cancel`: `DELETE admin/companies/{tenant}/invitations/{invitation}`

    Ambas con `auth`, `verified`, el Gate `manage-tenants` y `scopeBindings()`, para que una invitación de otra empresa
    dé 404. Solo actúan sobre una invitación abierta; si no lo está, responden 409.
  - `ResendInvitation::handle(TenantInvitation $invitation, User $by): TenantInvitation`:
    - genera un token nuevo, reemplaza `token_hash`, pone `expires_at = now + 7 días` e `invited_by = $by`
    - escribe el log y la bitácora `tenant_invitation.resent`
    - envía `OwnerInvitation` con `afterCommit()`
  - `CancelInvitation::handle(TenantInvitation $invitation): void`: pone `cancelled_at` y escribe el log y la bitácora
    `tenant_invitation.cancelled`.
  - Toasts: "Invitación reenviada." y "Invitación cancelada."
  - UI: "Reenviar" es un botón directo. "Cancelar" abre un Dialog de confirmación con el texto "El enlace dejará de
    funcionar."

- [ ] **Step 1: Escribir los tests que fallan**
- `it('resends with a new link that works while the old one no longer does')`:
  - el `token_hash` cambia y `expires_at` = now + 7 días
  - se envía una notificación nueva
  - `show` con el token viejo da `invalid` y con el nuevo da `new_account`
  - funciona también con una invitación que ya estaba vencida
- `it('cancels an open invitation so its link stops working')`.
- `it('answers 409 for an accepted or cancelled invitation')`.
- `it('answers 404 for an invitation of another company')`.
- `it('answers 404 to anyone who is not the superadmin')`.
- `it('logs and audits resend and cancel without the email, the name or the token')`.
- Browser: `it('cancels an invitation from the console')`.

- [ ] **Step 2:** Correr `php artisan test --compact tests/Feature/Tenancy/ResendCancelInvitationTest.php`. Expected: FAIL.
- [ ] **Step 3:** Implementar las acciones, las rutas, el controlador y la UI.
- [ ] **Step 4:** Correr esos tests y el Browser. Expected: PASS. Después correr `composer test`, `npm run test:js` y
  `npm run types:check`. Expected: verde.
- [ ] **Step 5:** Commit `Let the superadmin resend or cancel an owner invitation`.

---

### Task 6: Cambiar de owner

**Files:**
- Modify: `app/Modules/Tenancy/Actions/AcceptInvitation.php`
- Modify: `resources/js/components/tenancy/tenant-owner-cell.tsx`
- Test:
  - `tests/Feature/Tenancy/ChangeOwnerTest.php`
  - `tests/Browser/TenantConsoleTest.php` (cubre una fila con owner y una invitación pendiente)

**Interfaces:**
- Produces:
  - En `AcceptInvitation`, antes de crear la membresía, borra la membresía `owner` de otro usuario en esa empresa.
  - Escribe el log y la bitácora `tenant.owner_changed`, con metadata `previous_user_id` y `user_id`. Lo hace dentro del
    mismo `Context::scope`.
  - La celda Owner, con owner y una invitación abierta, muestra las dos líneas. Las acciones son las de la invitación:
    Reenviar y Cancelar.

- [ ] **Step 1: Escribir los tests que fallan**
- `it('keeps the current owner until the new one accepts')`: el owner sigue mientras la invitación está pendiente.
- `it('removes the previous owner from the company when the new one accepts')`: queda una sola membresía owner, la del
  nuevo usuario. El usuario anterior sigue existiendo y conserva su membresía en otra empresa.
- `it('does not restart the trial when the owner changes')`.
- `it('records the owner change with the previous and new user ids only')`.

- [ ] **Step 2:** Correr `php artisan test --compact tests/Feature/Tenancy/ChangeOwnerTest.php`. Expected: FAIL. El
  índice de un owner por empresa lanza `UniqueConstraintViolationException`.
- [ ] **Step 3:** Implementar el cambio en `AcceptInvitation` y en la celda.
- [ ] **Step 4:** Correr esos tests, `composer test:mutate`, `composer test`, `npm run test:js` y `npm run types:check`.
  Expected: verde, con mutación de al menos 85%.
- [ ] **Step 5:** Commit `Replace the company owner when a new owner accepts`.

---

## Cierre

- Revisión de toda la rama, push y PR con la plantilla. El push pide permiso.
- Prueba manual en Sail:
  1. Invitar desde `http://localhost/admin/companies`.
  2. Abrir el correo en `http://localhost:3550`.
  3. Abrir el enlace en una ventana privada y aceptar.
  4. Ver la empresa "En prueba" en la consola.
