# F6 · Consola: correcciones — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que la consola del superadmin diga la verdad sobre lo que hace (asignar plan, cambiar owner, límites) y que una empresa en solo lectura o en su tope vea qué puede hacer.

**Architecture:** Correcciones sobre lo que ya existe en Billing, Tenancy y Assistant; ninguna página nueva. Los puntos únicos se aprovechan: `ResolveTenant` comparte los permisos `can.*` que ya gobiernan cada botón (ahí se apagan en solo lectura y ahí se acota la impersonación), `SubscriptionView` arma el límite que muestra la cuenta, y `Module::label()` es el nombre de cada herramienta en la consola.

**Tech Stack:** Laravel 13, Pest, Vitest, Inertia + React. Sin migraciones ni dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (SA-01, SA-06..SA-19, HR-08; decisiones 14, 17, 31) · Specs: `2026-11-09-monetization-admin-design.md` (§4.2, §8), `2026-10-28-tenancy-invite-owner-design.md` («Cambiar de owner»), `2026-10-29-tenancy-company-url-design.md` (decisiones 3, 4 y 6), `2026-11-02-humi-assistant-design.md` («Objetivo», «Cuotas y costo»).

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario. PHPStan `max` y TS strictest sin supresiones ni `as`.
- El comportamiento de planes y módulos **no cambia**: los módulos siguen al plan (spec §4.2). Se corrige lo que la consola le dice al superadmin.
- Tenancy no lee modelos de Assistant ni de Billing: solo sus `Contracts`.
- Nunca se confía en un `tenant_id` del request; la empresa de la impersonación sale de la sesión.
- Acciones sensibles (cambio de owner, impersonar) siguen yendo a la bitácora con `RecordAudit`.
- Configuración nueva en `config/*.php` y `.env.example`, sin secretos.
- La consola tutea. Textos en es-MX.
- Referencia de diseño: la consola de empresas de `docs/design-system/pantallas/` y `docs/rediseno/pantallas/png/V3-Estados.png` (vacíos, errores y avisos). Claro, oscuro y 375 px.
- Rama `fix/launch-console-fixes`. F7 empieza cuando este frente está en `main`.

## Review Focus

1. **Asignar un plan que quita la herramienta que el owner está usando en ese momento.** La vista previa nombra las herramientas que se quitan y desde cuándo, antes de confirmar. → Task 1.
2. **Empresa en solo lectura con una pestaña abierta desde antes.** El servidor sigue respondiendo 423 a las escrituras; al recargar, los botones de alta ya no están. → Task 5.
3. **Impersonar al owner de la empresa A que también es owner de B.** `/admin/B` da 404 mientras dura la impersonación y el selector de empresas solo muestra A; al salir, el superadmin vuelve a su consola. → Task 7.
4. **Buscar algo que no existe con la consola vacía de verdad y con 300 empresas.** El primer caso dice «Aún no hay empresas»; el segundo, «Nada coincide» con el buscador a la vista. → Task 2.
5. **`SUPPORT_EMAIL` sin configurar.** Los avisos no muestran un enlace `mailto:` vacío: dicen «contacta a Humetria» sin enlace. → Task 5.

---

### Task 1: Asignar plan dice qué herramientas cambian (SA-01)

**Files:**
- Modify: `app/Modules/Billing/Actions/PreviewSubscription.php`, `Http/Controllers/SubscriptionPreviewController.php`, `resources/js/components/billing/subscription-dialog.tsx:43-44`, `resources/js/types/billing.ts`
- Test: `tests/Feature/Billing/SubscriptionModulesTest.php`, `tests/Browser/BillingAccountsTest.php`

**Interfaces:**
- La vista previa gana `modules: { added: string[]; removed: string[]; removedOn: string | null }` con las etiquetas de `Module::label()`, calculadas con la misma regla que `SetSubscription` → `syncPlan` (`SetSubscription.php:70-74`).

- [ ] **Step 1: Tests que fallan.** Empresa con todas las herramientas, vista previa del plan Cumplimiento → `removed` trae Asistencia, Vacaciones y Organigrama; `added` vacío; `removedOn` es el día que empieza el plan. Vista previa del mismo plan que ya tiene → ambos vacíos.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** El diálogo cambia «sus módulos siguen como están» por: «Las herramientas pasan a ser las del plan.» y, con la vista previa, «Se quitan el {fecha}: …» y «Se activan hoy: …». Si se quita alguna, el botón dice «Asignar y quitar N herramientas».
- [ ] **Step 6:** Browser: abrir el diálogo, elegir Cumplimiento, afirmar la lista.
- [ ] **Step 7: Commit** `Asignar plan: el diálogo nombra las herramientas que se activan y las que se quitan`.

### Task 2: Consola de empresas: búsqueda sin resultados y celular (SA-06, SA-14)

**Files:**
- Modify: `resources/js/pages/tenancy/index.tsx:37-42`, `resources/js/components/tenancy/tenants-empty.tsx`, `tenants-table.tsx:25-32`, `tenant-row.tsx:24-70`, `resources/js/lib/` (función `tenantsFiltered(counts, filters)`)
- Test: `tests/js/lib/` (test de esa función), `tests/Browser/TenantConsoleTest.php`

- [ ] **Step 1: Tests que fallan.** `tenantsFiltered({all: 0}, {q: 'zzz', status: 'all'})` → `true`; con `q: ''` y `status: 'all'` y `all: 0` → `false`. Browser: buscar «zzz» deja el buscador y las pestañas a la vista con «Nada coincide con tu búsqueda» y «Quitar filtros». A 375 px, la fila muestra nombre, RFC bajo el nombre, estado y el menú de acciones sin desplazar la tabla.
- [ ] **Step 2–4:** Falla → implementar (`hidden md:table-cell` en RFC y Dueño) → verde.
- [ ] **Step 5: Commit** (dos).

### Task 3: Cambiar de owner avisa a quien deja de serlo (SA-07)

**Files:**
- Create: `app/Modules/Tenancy/Notifications/OwnerReplaced.php`
- Modify: `app/Modules/Tenancy/Actions/AcceptInvitation.php:88-92,112-129`, `resources/js/components/tenancy/invite-owner-dialog.tsx:33-36`
- Test: `tests/Feature/Tenancy/ChangeOwnerTest.php`, `tests/Feature/MailInventoryTest.php`, `tests/Browser/TenantConsoleTest.php`

- [ ] **Step 1: Tests que fallan.** Al aceptar la invitación un owner nuevo, el anterior recibe `OwnerReplaced` («Ya no administras {empresa} en Humetria. Si crees que es un error, contesta este correo.») y no recibe nada si no había owner. El diálogo, cuando la empresa ya tiene owner, dice «Cuando acepte, {nombre} dejará de tener acceso a {empresa}.»
- [ ] **Step 2–4:** Falla → implementar (la notificación va en cola, después del commit de la transacción) → verde.
- [ ] **Step 5: Commit** `Cambiar de owner: aviso en el diálogo y correo a quien deja de serlo`.

### Task 4: Cuenta pagadora: límite real y datos fiscales del catálogo (SA-09, SA-10)

**Files:**
- Create: `app/Modules/Billing/Support/SatCatalogs.php` (constantes `TAX_REGIMES` 601…626 y `CFDI_USES` G01–G03, I01–I08, D01–D10, S01, CP01, CN01, con su etiqueta)
- Modify: `app/Modules/Billing/Support/SubscriptionView.php:26`, `Http/Requests/AccountRules.php:29,31`, `Actions/ExportReport.php:60`, `resources/js/components/billing/account-company-plan.tsx:64`, `account-fiscal-fields.tsx:21-41`, `resources/js/types/billing.ts`, `lang/es/validation.php`
- Test: `tests/Feature/Billing/SubscriptionLimitsTest.php`, `PortfolioTest.php`, `AccountsTest.php`, `tests/Unit/PaginatedListsTest.php` (los dos catálogos: «catálogo fijo del SAT»)

**Interfaces:**
- `SubscriptionView` gana `allowed_centers = max(centers, includedCenters(plan))`, la misma regla de `SetSubscription.php:78`.

- [ ] **Step 1: Tests que fallan.** Plan Cumplimiento con 1 centro contratado → `allowed_centers === 2` y la cuenta muestra «2 de 2 centros». Régimen `zzz` → 422; `601` → pasa; uso de CFDI `G03` → pasa, `X99` → 422.
- [ ] **Step 2–4:** Falla → implementar (`Select` con el catálogo) → verde.
- [ ] **Step 5: Commit** (dos).

### Task 5: Tope y solo lectura: botones coherentes y a quién escribir (SA-11, SA-13)

**Files:**
- Modify: `config/app.php` (`'support_email' => env('SUPPORT_EMAIL')`), `.env.example`, `app/Http/Middleware/HandleInertiaRequests.php` (prop compartida `support.email`), `app/Modules/Tenancy/Http/Middleware/ResolveTenant.php:77,85-112`, `app/Modules/Tenancy/Support/LimitMessage.php:22-23`
- Modify: `resources/js/lib/limits.ts:35,42`, `components/plan-limit-notice.tsx`, `pages/tenancy/work-centers.tsx:45-53`, `pages/organization/employees.tsx`, `components/company-read-only-notice.tsx:9-26`, `components/tenant-blocked-dialog.tsx:48`
- Test: `tests/Feature/Tenancy/TenantBlockTest.php`, `TenantStatusTest.php`, `CenterLimitsTest.php`, `CompanyLimitsTest.php`, `tests/js/lib/limits.test.ts`, `tests/Browser/PlanLimitsTest.php`

- [ ] **Step 1: Tests que fallan.**
  - Empresa en solo lectura → todas las llaves de escritura de `tenant.can` (`manage_*` y equivalentes) llegan en `false`; las de lectura no cambian. La escritura directa sigue dando 423.
  - En el tope de centros o de personas, el botón de alta llega deshabilitado (`planLimit.remaining === 0`) con el motivo.
  - `limits.test.ts`: con `support.email`, el aviso trae un enlace `mailto:` con el asunto «Ampliar {centros|personas} de {empresa}»; sin correo, el texto «contacta a Humetria» sin enlace.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** Los avisos de solo lectura y de empresa bloqueada usan el mismo enlace. Navegador: empresa en solo lectura en Centros, Empleados y Evaluaciones: sin botones de alta.
- [ ] **Step 6: Commit** (dos): `Solo lectura: los botones de alta desaparecen en toda la empresa` · `Tope del plan: el alta se deshabilita y el aviso dice a quién escribir`.

### Task 6: Humi apagada no se ofrece, y no manda a «tu sistema» (SA-08, HR-08)

**Files:**
- Create: `app/Modules/Assistant/Contracts/AssistantAvailability.php` (`available(): bool` para la empresa actual: `TenantAiSettings.enabled` y presupuesto disponible), su implementación en `Assistant/Support`
- Modify: `app/Modules/Tenancy/Http/Middleware/ResolveTenant.php:100` (`use_assistant` = permiso **y** disponibilidad), `resources/js/components/tenancy/humi-settings-fields.tsx:29`, el texto de sistema de Humi en `app/Modules/Assistant` (donde se arma el prompt)
- Test: `tests/Feature/Assistant/AllowanceTest.php`, `AiAccessTest.php`, `ChatTest.php`, `tests/Browser/AssistantTest.php`

- [ ] **Step 1: Tests que fallan.** Con Humi apagada para la empresa → `tenant.can.use_assistant === false` y el dock no se monta (browser). El chat directo sigue respondiendo 403. La casilla de la consola dice «Activar Humi para esta empresa». El texto de sistema incluye la regla: ante preguntas de vacaciones, asistencia o personas, decir que aún no puede leer esos datos y señalar la pantalla de Humetria donde están; nunca remitir a «el sistema de tu empresa» (test sobre el texto generado).
- [ ] **Step 2–4:** Falla → implementar → verde. `ResolveTenant` fija su número de consultas: como mucho una más.
- [ ] **Step 5: Commit** (dos).

### Task 7: La impersonación se queda en la empresa elegida (SA-12, decisión 14)

**Files:**
- Modify: `app/Http/Impersonation.php:55-64` (guarda `impersonated_tenant_id`), `app/Modules/Tenancy/Http/Controllers/ImpersonateOwnerController.php:28`, `app/Modules/Tenancy/Http/Middleware/ResolveTenant.php`, la prop del selector de empresas
- Test: `tests/Feature/ImpersonationTest.php`, `tests/Feature/Tenancy/ImpersonatedBlockTest.php`

- [ ] **Step 1: Tests que fallan.** Impersonando al owner de A (que también lo es de B): `/admin/A` → 200; `/admin/B` → 404; el selector de empresas trae solo A. Al salir, la sesión no conserva `impersonated_tenant_id`. Sin impersonar, el consultor ve A y B como hoy.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** Anotar en el PR que esto cambia la decisión 3 del spec company-url y actualizar esa línea del spec.
- [ ] **Step 6: Commit** `Impersonar entra solo a la empresa desde la que se inició`.

### Task 8: Nombres y etiquetas de la consola (SA-15..SA-19)

Un commit por fila.

| Hallazgo | Archivos | Cambio | Test |
|---|---|---|---|
| SA-15 | `Http/Controllers/TenantConsoleController.php:45-68`, `CompanyModulesController.php:35-44`, `tenant-row.tsx`, `pages/tenancy/company.tsx` | Prop `type` y `Badge` «Sindicato» junto al nombre | `tests/Feature/Tenancy/TenantTypeTest.php`, `TenantConsoleTest.php` |
| SA-16 | `app/Modules/Tenancy/Enums/Module.php:25-33` | `label()` igual que el menú: «Asistencia», «Quejas», «Organigrama», «Clima» | `tests/Feature/Tenancy/CompanyModulesConsoleTest.php`, `tests/Browser/CompanyModulesTest.php` (cambian porque cambia el comportamiento esperado: decirlo en el commit) |
| SA-17 | `app/Modules/Tenancy/Enums/ModuleSource.php:23` | «Incluido antes del plan» → «Se quita al empezar el plan» con su fecha | `CompanyModulesConsoleTest.php` |
| SA-18 | `app/Modules/Tenancy/Notifications/InvitationEmail.php:61-62` | Texto neutro: «administrarás la cuenta, sus usuarios y las herramientas que tenga contratadas» | `tests/Feature/Tenancy/InvitationEmailTest.php` |
| SA-19 | `components/tenancy/tenant-form-fields.tsx:58`, `lang/es/validation.php:466` | «Dirección» → «Dirección web», con la ayuda «Así se verá: …/admin/{slug}» | `tests/Feature/Tenancy/CreateTenantTest.php`, `tests/Browser/TenantConsoleTest.php` |

- [ ] Por cada fila: actualizar la aserción al texto nuevo (cambio de comportamiento pedido), verla fallar, cambiar, verde, commit.

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Recorrido como superadmin: asignar plan, cambiar owner, solo lectura, tope, impersonar a un consultor; claro, oscuro, 375 px.
- [ ] Preguntar «¿Creo el PR?».
