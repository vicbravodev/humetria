# F4 · Reglas de RH del día a día — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que las operaciones diarias de RH respeten la LFT (baja con fecha y motivo, salario mínimo, saldos al día) y que encontrar a una persona tome un atajo de teclado.

**Architecture:** Cada regla va en el módulo dueño. La fecha efectiva de baja viaja en el evento público `Organization\Events\EmployeeDeactivated` y Leave la usa para el finiquito. La búsqueda mejora una sola vez en `App\Concerns\SearchesText` (todas las listas ganan). Lo que cruza módulos usa contratos nuevos y pequeños: `Leave\Contracts\VacationTerms` (para el aviso de Compensación) y `Leave\Contracts\PendingRequests` (para el Panel).

**Tech Stack:** Laravel 13, Pest, Vitest, Inertia + React, Postgres. Sin dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (HR-01, HR-02, HR-04..HR-07, HR-09..HR-12, HR-14, HR-15, HR-16, H-08, C-13, C-19, E-17a; decisiones 6, 7, 27, 34, 36) · **Mandan:** `docs/normas/vacaciones/criterios.md` (C-07, C-17, C-21), `docs/normas/compensation/criterios.md` · Specs: `2026-11-09-vacations-orgchart-pulse-climate-design.md`, `2026-11-02-collective-contract-calculator-design.md`.

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario. PHPStan `max` y TS strictest sin supresiones ni `as`.
- Otro módulo solo por `Contracts`, `Events` y `Data`. Compensation y Tenancy no importan modelos de Leave.
- **Ningún importe en dinero entra a Leave** (regla de su plan): el finiquito sigue en días.
- `leave_entries` es de solo inserción: la fecha efectiva cambia qué se inserta, no reescribe nada.
- Días como `date` en el día local de `config('app.display_timezone')`; «hoy» es el día local.
- El aviso de salario mínimo **no bloquea** (decisión 6). El motivo de baja es un catálogo cerrado (decisión 7).
- Listas paginadas en el servidor; la búsqueda de personas en ⌘K usa el endpoint existente, no un arreglo completo.
- Migración con prefijo `2027_01_14_*`; integridad con `CHECK` además de la validación. Atributos nuevos de FormRequest en `lang/es/validation.php` → `attributes`.
- Toda acción que cambia estado escribe su log `modulo.accion`; la baja va además a la bitácora.
- Referencias de diseño: la pantalla de Empleados de `docs/design-system/pantallas/`, `docs/rediseno/pantallas/png/V4-Comp-Datos.png`, `V4-Menu-Roles.png`, `V2-Panel.png`. Claro, oscuro y 375 px.
- Rama `fix/launch-hr-rules`. Este frente es el único que toca `SearchesText`, `command-palette*.tsx`, `lib/mobile-tabs.ts` y `Tenancy\Support\PanelUrgencies`.

## Review Focus

1. **Baja con fecha de hace tres semanas, con una solicitud de vacaciones aprobada posterior a esa fecha.** El finiquito cuenta los días a la fecha efectiva; la solicitud posterior no se descuenta como gozada. → Task 1.
2. **Baja y reactivación el mismo día, y baja con fecha anterior al ingreso.** La reactivación limpia fecha y motivo y anula el finiquito; la fecha anterior al ingreso se rechaza. → Task 1.
3. **Buscar «perez juan», «55 1234 5678» y «Pérez» con 5,000 personas.** Encuentra en cualquier orden, sin acentos, por celular con o sin espacios, y sigue paginando en el servidor. → Task 5.
4. **Alta por CSV de 200 personas con antigüedad.** Cada una tiene sus días al terminar la importación, sin duplicar periodos cuando corra el job de las 00:30. → Task 3.
5. **Usuario sin permiso de organización que abre ⌘K.** No ve el grupo «Personas» y el endpoint le da 403. → Task 6.

---

### Task 1: Baja con fecha efectiva y motivo (HR-01)

**Files:**
- Create: `database/migrations/2027_01_14_100000_add_termination_to_employees_table.php` (`terminated_on` `date` nullable; `deactivation_reason` `string(20)` nullable; `CHECK` con los valores del enum; `CHECK (terminated_on IS NULL) = (deactivated_at IS NULL)`), `app/Modules/Organization/Enums/DeactivationReason.php`
- Modify: `app/Modules/Organization/Http/Requests/DeactivateEmployeeRequest.php:29`, `Actions/DeactivateEmployee.php:33`, `Events/EmployeeDeactivated.php:13`, la acción de reactivar, `Http/Controllers/EmployeeController.php:105`, `app/Modules/Leave/Listeners/CloseOnDeactivation.php:30,43`, `app/Modules/Leave/Actions/RecordTermination.php:42,56,57`, `lang/es/validation.php`, `resources/js/components/organization/deactivate-employee-dialog.tsx`
- Test: `tests/Feature/Organization/DeactivateWithReportsTest.php`, `EmployeeEventsTest.php`, `tests/Feature/Leave/TerminationTest.php`, `DeactivationTest.php`, `tests/Browser/EmployeesTest.php`

**Interfaces:**
- `enum DeactivationReason: string` — `Resignation` «Renuncia», `Dismissal` «Rescisión o despido», `ContractEnd` «Término de contrato», `MutualAgreement` «Mutuo acuerdo», `Death` «Fallecimiento», `Other` «Otro».
- `EmployeeDeactivated(int $tenantId, int $employeeId, CarbonImmutable $effectiveOn)`.
- `RecordTermination::handle(int $employeeId, CarbonImmutable $effectiveOn): void` — `terminated_on = $effectiveOn`; los días pendientes se calculan a esa fecha.
- Request: `terminated_on` (`required`, `date`, `before_or_equal:` hoy local, `after_or_equal:` fecha de ingreso), `reason` (`required`, `Rule::enum`), `reassign_to` como hoy.

- [ ] **Step 1: Tests que fallan.**
  - Organization: sin fecha o sin motivo → 422 con «fecha de baja» y «motivo»; fecha de mañana → 422; fecha anterior al ingreso → 422; fecha de hoy y de hace 21 días → guarda `terminated_on` y `deactivation_reason`, y el evento lleva `effectiveOn`. Reactivar deja ambos en `null`. Bitácora `employee.deactivated` con `reason` y `terminated_on`.
  - Leave (`TerminationTest`): baja con fecha de hace 21 días → el registro de finiquito tiene `terminated_on` de esa fecha y los días proporcionales a ella, distintos de los de hoy. Solicitud aprobada con fechas posteriores a la baja → no cuenta como gozada.
- [ ] **Step 2:** Correr → fallan por columnas y firma.
- [ ] **Step 3:** Migración, enum, request, acción, evento. Luego Leave.
- [ ] **Step 4:** Diálogo: `<input type="date">` con hoy por omisión y `max` hoy, y un `Select` de motivo; el texto explica «Los días de vacaciones pendientes se calculan a esta fecha.»
- [ ] **Step 5:** Verde en los cinco archivos. Browser: dar de baja con fecha de la semana pasada.
- [ ] **Step 6: Commit** (dos): `Baja de una persona con fecha efectiva y motivo` · `Vacaciones: el finiquito de días usa la fecha efectiva de la baja`.

### Task 2: Aviso de salario bajo el mínimo (HR-02)

**Files:**
- Modify: `app/Modules/Compensation/Support/SalaryRules.php`, `Support/FiscalParameters.php` (lectura del mínimo a una fecha), `Actions/SaveGradeSalary.php:28`, `SaveSalaryOverride.php:30`, `TabulatorCsv.php:38`, `OverridesCsv.php:39`, los formularios de tabulador y de sueldo individual en `resources/js/components/compensation`
- Test: `tests/Feature/Compensation/LegalMinimumTest.php`, `TabulatorTest.php`, `TabulatorImportTest.php`, `SalaryOverrideTest.php`

**Interfaces:**
- `SalaryRules::salaryWarning(int $cents, int $minimumCents, int $borderCents): ?string` — `null` si `$cents >= $minimumCents`; si no: «Está por debajo del salario mínimo general (\$315.04 diarios; \$440.87 en la zona libre de la frontera norte). Revísalo si la jornada es completa.» con los importes de los parámetros vigentes.

- [ ] **Step 1: Tests que fallan.** Límite exacto: `31503` → aviso, `31504` → `null`. Guardar \$250 en el tabulador → **se guarda** y la respuesta trae el aviso; en la importación CSV la fila sale en la revisión con el aviso y no como error. El mínimo se toma a la fecha de vigencia del tabulador, no a hoy (caso con dos vigencias).
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** La fila con aviso usa el tono de advertencia del design system y conserva el valor.
- [ ] **Step 6: Commit** `Compensación: avisa cuando un salario queda bajo el mínimo general vigente`.

### Task 3: Vacaciones al día: alta con antigüedad y solicitudes sin saldo (HR-05, HR-09)

**Files:**
- Modify: `app/Modules/Leave/Actions/GrantEntitlements.php:96-101` (extraer `grantFor(int $employeeId): int`), `app/Modules/Leave/Providers/LeaveServiceProvider.php:30-31`, crear `app/Modules/Leave/Listeners/GrantOnHire.php` (escucha `EmployeeCreated` y `EmployeeReactivated`)
- Modify: `app/Modules/Leave/Support/RequestPages.php` (prop `canApprove`, igual que `PortalTeamPages.php:55,68`), `resources/js/components/leave/request-actions.tsx`, `decision-card.tsx`, `to-answer-card.tsx`, `types/leave.ts`
- Test: `tests/Feature/Leave/GrantEntitlementsTest.php`, `RequestsPanelTest.php`, `RequestDecisionPanelTest.php`, `tests/js/lib/leave/queue.test.ts`

- [ ] **Step 1: Tests que fallan.**
  - Alta de una persona con dos aniversarios posteriores a `tracking_since` → al terminar el alta tiene sus dos periodos. Correr después `leave:grant-entitlements` no los duplica. Aniversarios anteriores a `tracking_since` siguen sin otorgarse (C-21).
  - Solicitud sin saldo y política sin adelanto → `canApprove === false` y el texto «No tiene días y su política no permite adelantarlos: recházala o ajusta su saldo.» Con adelanto permitido → `true`.
- [ ] **Step 2–4:** Falla → implementar → verde. El listener corre dentro de la empresa del evento (`tenantId`).
- [ ] **Step 5: Commit** (dos): `Vacaciones: quien entra con antigüedad tiene sus días al momento del alta` · `Vacaciones: RH no ve «Aprobar» en una solicitud que el servidor va a rechazar`.

### Task 4: Importación de personas: «M» ambigua y qué cambia (H-08, HR-12)

**Files:**
- Modify: `app/Modules/Organization/Enums/Gender.php:22`, `Actions/PlanEmployeeImport.php:163-178`, `Data/EmployeeImportRow.php`, `Data/EmployeeImportPlan.php`, `Http/Controllers/EmployeeImportController.php`, `resources/js/components/organization/import-stats.tsx` y un componente nuevo `import-changes.tsx`
- Test: `tests/Feature/Organization/EmployeeImportTest.php`, `tests/Browser/EmployeeImportTest.php`

**Interfaces:**
- `EmployeeImportRow` gana `changes: list<array{label: string, before: string, after: string}>` (vacío en altas). Las columnas ausentes del archivo no generan cambio.

- [ ] **Step 1: Tests que fallan.** `genero=M` → error de fila «"M" puede ser Masculino o Mujer: escribe Hombre o Mujer (o H / F).» y no se importa nada. `F`, `H`, `Mujer`, `Masculino` siguen pasando. Actualizar a una persona cambiando área y celular → `changes` trae esos dos con antes y después; una columna que el archivo no trae no aparece.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** En la revisión, bajo «Se actualizan N», lista plegable por persona con `campo: antes → después`, paginada con la misma página de la revisión.
- [ ] **Step 6: Commit** (dos).

### Task 5: Buscar personas por palabras en cualquier orden y por celular (HR-11)

**Files:**
- Modify: `app/Concerns/SearchesText.php:28-33`, `app/Modules/Organization/Actions/ListEmployees.php:37`, `app/Modules/Organization/Support/CompanyRoster.php:104`
- Test: `tests/Feature/Organization/EmployeeListTest.php`, `RosterTest.php`, y el test de otro consumidor del trait (áreas o tabulador)

**Interfaces:**
- `SearchesText`: la consulta se parte por espacios; cada palabra debe aparecer en alguna de las columnas (AND entre palabras, OR entre columnas), sin distinguir mayúsculas ni acentos. Una consulta vacía o de solo espacios no filtra.
- En las dos consultas de personas se agrega `mobile`, comparando contra los dígitos de la consulta cuando trae al menos 4.

- [ ] **Step 1: Tests que fallan.** «perez juan» encuentra a «Juan Pérez»; «juan lopez» no encuentra a «Juan Pérez»; «55 1234 5678» y «5512345678» encuentran por celular; «%» y «_» se tratan como texto; consulta de 200 caracteres no revienta.
- [ ] **Step 2–4:** Falla → implementar → verde. Correr `tests/Feature --filter=Search` para los demás consumidores.
- [ ] **Step 5: Commit** `Búsqueda: palabras en cualquier orden en todas las listas, y por celular en Empleados`.

### Task 6: ⌘K encuentra personas (HR-04)

**Files:**
- Create: `resources/js/hooks/use-palette-people.ts`
- Modify: `resources/js/components/command-palette.tsx:31-45`, `command-palette-results.tsx`, `resources/js/lib/palette-commands.ts`
- Test: `tests/js/lib/palette-commands.test.ts`, `tests/Browser/EmployeesTest.php`

**Interfaces:**
- `usePalettePeople(query: string): { people: PalettePerson[]; loading: boolean }` — con 2 o más caracteres llama a `tenant.org-chart.people.search` mediante `serverSource` (`lib/server-options.ts`), con espera de 200 ms y cancelando la petición anterior. Solo se monta si `tenant.can.manage_organization`.
- `peopleCommands(people, tenantSlug): PaletteCommand[]` en `lib/palette-commands.ts` — cada persona lleva a `employees?q=<nombre>` (decisión 25: aún no hay ficha).

- [ ] **Step 1: Tests que fallan.** `peopleCommands` arma grupo «Personas» con nombre y puesto. Browser: ⌘K, escribir «pér», aparece la persona, Enter lleva a Empleados filtrado. Con rol viewer no aparece el grupo.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** `⌘K busca personas además de páginas`.

### Task 7: Organigrama legible al abrir y búsqueda que no saca de edición (HR-10)

**Files:**
- Modify: `resources/js/lib/org-chart/camera.ts:1,19-27`, `lib/org-chart/flow.ts`, `lib/org-chart/labels.ts:21-32`, `components/organization/org-person-search.tsx:27-33`, `hooks/use-org-editor.ts:53`
- Test: `tests/js/lib/org-chart/camera.test.ts`, `labels.test.ts`, `tests/Browser/OrgChartTest.php`

- [ ] **Step 1: Tests que fallan.** `camera.test.ts`: con un árbol ancho, el encuadre inicial contiene la raíz y su primer nivel con zoom ≥ 0.7, anclado arriba. `labels.test.ts`: `orgChartQuery` conserva `edit=1`. Browser: en edición, buscar a una persona deja el modo edición activo.
- [ ] **Step 2–4:** Falla → implementar (`editing` parte de `edit=1`) → verde.
- [ ] **Step 5: Commit** (dos).

### Task 8: Compensación: etiquetas honestas y aviso de política distinta (HR-06, HR-07, HR-15)

**Files:**
- Create: `app/Modules/Leave/Contracts/VacationTerms.php`, `app/Modules/Leave/Data/CompanyVacationTerms.php`, su implementación en `Leave/Support`
- Modify: `app/Modules/Compensation/Http/Controllers/CompensationController.php` (llama `BaseCatalog::ensure()`), `Support/BenefitRows.php` (etiqueta y aviso), los 7 archivos con «Cada año después» (`components/compensation/scenario-cost-aside.tsx:59`, `compare-card.tsx:30`, `compare-distance.tsx:55`, `scenario-compare-table.tsx:87`, `scenario-start-row.tsx:55`, `lib/compensation/report.ts:79`, `pages/compensation/index.tsx:34`)
- Test: `tests/Feature/Compensation/CompensationIndexTest.php`, `BaseCatalogTest.php`, `tests/js/lib/compensation/report.test.ts`, `tests/Browser/CompensationTest.php`

**Interfaces:**
- `VacationTerms::company(): ?CompanyVacationTerms` — `null` si la empresa no usa Vacaciones. `CompanyVacationTerms(int $firstYearDays, int $premiumPercent)`.

- [ ] **Step 1: Tests que fallan.** El inicio de Compensación, sin haber abierto Prestaciones, ya cuenta las prestaciones base. La prima de antigüedad base dice «Adicional a la de ley (los 12 días por año se calculan aparte)». Con una política de Vacaciones de 15 días y 30 % y el contrato en 12 y 25 %, Prestaciones muestra el aviso «Tu política de Vacaciones da 15 días y 30 % de prima; aquí el contrato dice 12 y 25 %.»; si coinciden o la empresa no usa Vacaciones, no hay aviso. `report.test.ts`: la etiqueta es «Al año, al arranque» con la nota «Los años siguientes cambian: mira la proyección».
- [ ] **Step 2–4:** Falla → implementar → verde. El cálculo de costos no cambia (decisión 36).
- [ ] **Step 5: Commit** (tres, uno por hallazgo).

### Task 9: Plurales y pestaña de operación en el celular (HR-14, HR-16)

**Files:**
- Modify: `resources/js/lib/option-search.ts:77`, `lib/org-chart/canvas-labels.ts:57`, `lib/mobile-tabs.ts:15-46`, `components/mobile-tab-bar.tsx`
- Test: `tests/js/lib/option-search.test.ts`, `tests/js/lib/org-chart/canvas-labels.test.ts`, `tests/js/lib/mobile-tabs.test.ts`

- [ ] **Step 1: Tests que fallan.** «Escribe al menos 1 letra» / «2 letras». «Otra persona no tiene…» / «Otras 3 no tienen…». Con herramientas de operación y permiso, la barra trae «Operación» (Empleados, Vacaciones, Asistencia) y nunca pasa de 5 pestañas; sin ellas, queda como hoy.
- [ ] **Step 2–4:** Falla → implementar → verde. Revisar a 375 px contra `V4-Menu-Roles.png`.
- [ ] **Step 5: Commit** (dos).

### Task 10: Clima y pulso: participación, tendencia y permisos (C-13, C-19, E-17a)

**Files:**
- Modify: `app/Modules/Engagement/Support/SurveyPages.php:228-239` (`countOf`), `Support/Pulse/TrendPage.php:39-44,105-110`, `Http/Requests/CommitmentRequest.php:17-20`, `CommitmentStatusRequest.php:13-16`
- Test: `tests/Feature/Engagement/SurveyPagesTest.php`, `TrendTest.php`, `CommitmentsTest.php`, `tests/js/lib/engagement/pulse.test.ts`

- [ ] **Step 1: Tests que fallan.** Historial de una encuesta cerrada con 68 de 120 respuestas → «68 de 120», igual que Resultados. Dos olas cerradas el mismo mes → etiquetas distintas («nov 2026 · Pulso 1», «nov 2026 · Pulso 2»), nunca el día. Viewer con cuerpo inválido en compromisos → 403, no 422.
- [ ] **Step 2–4:** Falla → implementar (`authorize()` como `LaunchSurveyRequest.php:14-18`) → verde. Si el primer test pasa sin cambios, se deja como red de seguridad y se anota en el PR que C-13 era un dato sembrado.
- [ ] **Step 5: Commit** (tres).

### Task 11: El Panel avisa de solicitudes de vacaciones por responder (decisión 27)

**Files:**
- Create: `app/Modules/Leave/Contracts/PendingRequests.php` (`waiting(): int`), su implementación
- Modify: `app/Modules/Tenancy/Support/PanelUrgencies.php`, `DashboardPage.php:60-75`
- Test: `tests/Feature/Tenancy/DashboardTest.php` (o el test del Panel), `tests/Browser/DashboardTest.php`

- [ ] **Step 1: Tests que fallan.** Con 3 solicitudes en espera y permiso `manage-vacations` → urgencia «3 solicitudes de vacaciones esperan respuesta» con enlace a Solicitudes. Sin la herramienta, sin permiso o con 0 → no aparece. El Panel sube exactamente una consulta (`expectsDatabaseQueryCount`).
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** `Panel: solicitudes de vacaciones por responder entre lo urgente`.

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Recorrido como Carlos (rol hr): alta, baja con fecha, tabulador, búsqueda, ⌘K, organigrama; claro, oscuro, 375 px.
- [ ] Preguntar «¿Creo el PR?».
