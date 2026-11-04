# Calculadora de contrato colectivo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** que una empresa o un sindicato costee una propuesta de revisión de contrato colectivo (salario y prestaciones) y vea un reporte de corto plazo, largo plazo y prima de antigüedad, con todos sus supuestos.

**Architecture:** `tenants` gana un tipo (`company` | `union`). El padrón (`Organization`) se relaja y gana categoría/nivel, ingreso y grupo, sin dinero. El dinero, el cálculo, los escenarios y el reporte viven en un módulo nuevo, `app/Modules/Compensation`, que lee el padrón solo por un contrato nuevo de `Organization` con **conteos agrupados**. El cálculo es un conjunto de clases puras en `Compensation/Support/Calculation`, con centavos enteros. Las herramientas de cumplimiento se ocultan a las cuentas de sindicato por un registro de rutas con una prueba que obliga a registrar las nuevas.

**Tech Stack:** Laravel 13 + Pest 4 (Postgres), Inertia v3 + React + TypeScript strictest, Tailwind v4 + shadcn, Spatie permission (teams). **Una sola dependencia, aprobada por el usuario (2026-11-03): `brick/math`**, declarada directa (`composer require brick/math`, Task 8) para el dinero; ninguna otra sin aprobación.

**Spec:** [`docs/superpowers/specs/2026-11-02-collective-contract-calculator-design.md`](../specs/2026-11-02-collective-contract-calculator-design.md). Quien ejecute lo lee completo. Modelo de estilo: `2026-11-02-ley-silla.md`.

## Global Constraints

- CLAUDE.md completo aplica: TDD estricto, Larastan `max` sin supresiones, TS sin `as`/`!`/`any`, clases `final`, identificadores en inglés, UI en español (es-MX, el panel tutea), `timestampTz`, UUID v4, `tenant_id` + `BelongsToCurrentTenant` + llaves compuestas `(id, tenant_id)`, listas paginadas en el servidor, `DB::transaction()` en operaciones de varios pasos, logs `modulo.accion` en pasado con datos solo en el contexto, `RecordAudit` en acciones sensibles, factories en `database/factories/Compensation/`, cada pantalla nueva con `visitPage()` y parte de la pantalla más parecida de `docs/design-system/pantallas/`.
- Rama `claude/calculadora-contrato-colectivo-8a6dc9`. **Un commit = un comportamiento** con su prueba, cada commit en verde. Sin push ni PR hasta que el usuario diga «crea el PR». Commits sin atribución.
- Reglas de desarrollo y CI vigentes desde que se trajo `main` (PR #76 y #77, ver CLAUDE.md «Definición de terminado» y «Entorno local»):
  cada worktree prueba contra su propia base (`testing_<directorio>_<hash>`, la crea sola `tests/bootstrap.php`; un `DB_DATABASE` explícito en el shell gana; `composer worktree:setup` prepara un worktree nuevo);
  mientras se trabaja basta lo acotado (`php artisan test --compact <archivo|--filter>`) y `composer check:fast` (Pint + PHPStan + Tia, es lo que corre el hook de Stop **en este worktree**);
  `composer ci:check` (suite completa con cobertura en paralelo, navegador, `npm run types:check`, `npm run test:js`) se corre **una vez al cerrar el trabajo**, antes del «crea el PR», no tras cada tarea; el CI de GitHub la repite en paralelo (jobs `checks`, `tests`, `browser`).
  Dos agentes que trabajen a la vez en este mismo worktree usan cada uno su `DB_DATABASE` explícito (`testing_ccc`, `testing_ccc_p2`) para no pisarse.
- Archivos de más de 300 líneas, funciones de más de 100, complejidad 10: se parten, no se sube el límite (`vp lint`).
- `main` trajo el rol `complaints` (`TenantRole::Complaints`), `LimitRestrictedRoles` y los módulos `Complaints`, `Disclosure` y la ruta `tenant.interventions.*`: todos son herramientas de cumplimiento (ya en `ComplianceRoutes`); una cuenta de sindicato solo asigna `owner` y `hr`.
- **Dinero en centavos enteros** (`int`) y aritmética exacta con `Brick\Math\BigRational` donde haya fracciones (factor de integración, tasas); **cero flotantes** en `Compensation/Support/Calculation`. Redondeo: se **trunca al centavo** el SBC (así el ejemplo del spec da $524.65, no $524.66) y se redondea al centavo más cercano el resto; la regla vive en `Money`. *Decisión del plan, aprobada por el usuario el 2026-11-03.*
- Permisos: `view-compensation` y `manage-compensation`, solo `owner` y `hr`. Consulta y Comisión no ven dinero. Rutas `admin/{tenant:slug}/compensation/...`, nombre `tenant.compensation.*`.
- Tipo de cuenta: `company` (todas las existentes) o `union`; no cambia después de crearla. Roles de una cuenta de sindicato: solo `owner` y `hr`.
- Antes de salir de `Compensation`, un salario nunca va en: logs, bitácora, props de páginas ajenas, ni resultados de encuestas. Compensación responde 404 durante una suplantación.
- Todo reporte o exportación por persona usa `employee_number` («ID de la empresa»), nunca el `id`.
- Mínimo del reporte compartible: el de la plataforma (`results.min_group`, por defecto 5), con supresión complementaria, vía un contrato de `Results` (Task 17).
- Tasas fiscales: solo las del documento `docs/normas/compensation/criterios.md` (Task 1), con fuente y fecha. Los ejemplos de las pruebas usan valores de 2026.

## Review Focus

1. **Sueldo individual huérfano o repetido** (ID de la empresa inexistente, dos vigencias, persona dada de baja): se rechaza o queda fuera del conteo y el reporte lo señala → Task 10 y 13.
2. **Cuenta de sindicato que abre una URL de cumplimiento por id** (campaña, centro, evaluación de otra cuenta): 404, no 403 ni redirección → Task 3.
3. **Reconstruir un grupo oculto** restando subtotales del total en el enlace compartible, o con un solo grupo visible → Task 17.
4. **Aniversario exacto** (ingreso el 29 de febrero, ingreso el mismo día del escenario, antigüedad de 0 años): los días de vacaciones y la prima cambian el día correcto y no hay división entre cero → Task 11.
5. **Padrón vacío o sin categoría/ingreso, escenario sin cambios, retroactividad cero, SBC justo en 25 UMA y un centavo arriba**: costo cero o tope, nunca excepción → Tasks 11 a 14.

---

## File Structure

```
app/Modules/Tenancy/       Enums/TenantType.php, Support/ComplianceRoutes.php, Http/Middleware/LimitUnionToCompensation.php
app/Modules/Organization/  Models/JobGrade.php, Enums/StaffGroup.php, Contracts/{JobGrades,CompensationRoster}.php,
                           Data/{HeadcountCell,CompensationPerson,RosterGaps}.php, Support/{CompanyJobGrades,CompanyCompensationRoster}.php
app/Modules/Results/       Contracts/SmallGroups.php  (la regla del mínimo, para otros módulos)
app/Modules/Compensation/
  Actions/ Models/ Enums/ Data/ Http/{Controllers,Requests,Middleware}/ Providers/CompensationServiceProvider.php
  Support/Calculation/{Money,IntegrationFactor,SalaryBase,EmployerContributions,BenefitCost,ScenarioCost,Projection,SeniorityPremium,Sensitivity}.php
  Support/{FiscalParameters,BaseCatalog,TabulatorCsv,OverridesCsv,ReportBuilder,SharedGroups}.php
routes/compensation.php (incluido desde routes/company.php) y routes/compensation-shared.php (público)
database/factories/Compensation/  database/migrations/2026_11_3x_*
resources/js/pages/compensation/*.tsx  components/compensation/*.tsx  lib/compensation/*.ts
tests/Feature/Compensation/*  tests/Unit/Compensation/*  tests/Browser/CompensationTest.php  tests/js/lib/compensation/*
docs/normas/compensation/{criterios.md,fuentes/}
```

Modelos nuevos con `tenant_id`: `JobGrade` (Organization); `GradeSalary`, `Benefit`, `ContractTerm`, `SalaryOverride`, `Assumption`, `Scenario`, `ReportShare` (Compensation). `FiscalParameter` es de la plataforma (sin `tenant_id`; entra a las excepciones de `TenantScopedModelsTest` con su razón).

---

### Task 1: Criterios fiscales verificados

**Files:**
- Create: `docs/normas/compensation/criterios.md`, `docs/normas/compensation/fuentes/*` (extractos con URL y fecha de consulta)

**Interfaces:**
- Produces: la tabla de valores que sembrará la Task 8, cada uno con **valor, vigencia, fuente oficial (DOF, IMSS, INEGI, CONASAMI, LSS/RLSS) y fecha de consulta**: UMA diaria, salario mínimo general, tope del SBC (25 UMA), cuotas patronales del IMSS (cuota fija enfermedad y maternidad, excedente, gastos médicos de pensionados, prestaciones en dinero, invalidez y vida, guarderías, retiro, cesantía y vejez por rango de SBC y año 2023-2030), Infonavit, mínimos de ley (aguinaldo 15 días, prima vacacional 25 %, tabla de vacaciones LFT art. 76, prima de antigüedad LFT art. 162 con tope de 2 salarios mínimos), vales de despensa exentos (40 % UMA), ISN por estado (tasa y base) para los 32 estados.

- [ ] **Step 1:** buscar cada valor en su fuente **oficial** (no secundaria) con WebFetch/WebSearch; guardar el extracto en `fuentes/`.
- [ ] **Step 2:** escribir `criterios.md` con la tabla, la fórmula del factor de integración y los ejemplos trabajados del spec (500 diarios → SBC $524.65; prima de antigüedad $120,000 → $126,000 con +5 % y 20 años), más lo que **no** se pudo confirmar (se marca «por verificar», no se inventa).
- [ ] **Step 3: Commit** `git add docs/normas/compensation && git commit -m "Calculadora: criterios fiscales con fuentes oficiales"`.

### Task 2: Tipo de cuenta `union`

**Files:**
- Create: migración `2026_11_30_100000_add_type_to_tenants.php`, `app/Modules/Tenancy/Enums/TenantType.php`
- Modify: `Tenant` (cast, fillable, `isUnion()`), `TenantData` (`type`), `CreateTenant`, `StoreTenantRequest`, `Tenancy/Contracts/Companies` y su implementación (expone `type` en `CompanySummary`), `InviteMember`/`ChangeMemberRole` (rechazan `viewer`/`committee` en sindicato), `resources/js/pages/tenancy/{index,companies}.tsx` (selector de tipo al dar de alta; solo lectura después), `ResolveTenant` (comparte `tenant.type`), `UpdateTenantRequest` (ignora `type`)
- Test: `tests/Feature/Tenancy/TenantTypeTest.php`

**Interfaces:**
- Produces: `enum TenantType: string { Company='company'; Union='union' }`; `tenants.type` `not null default 'company'` con `check`; `CompanySummary::$type: TenantType`; prop `tenant.type: 'company'|'union'`.

- [ ] **Step 1: Pruebas que fallan.** `it creates a union from the console and defaults to company`; `it rejects an unknown type`; `it never changes the type on update`; `it refuses to invite a viewer or a committee member to a union and to change a member to those roles`; `it keeps existing companies as company`; `it exposes tenant.type in the shared props`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Tenancy/TenantTypeTest.php` → FAIL.
- [ ] **Step 3: Implementar.** El tipo viaja en `TenantData`; las reglas de rol de una cuenta de sindicato se validan en las acciones de membresía (la fuente de verdad), no solo en el FormRequest.
- [ ] **Step 4:** pasa; `composer test:fast`; `npm run types:check`.
- [ ] **Step 5: Commit** `"Calculadora: cuentas de empresa y de sindicato"`.

### Task 3: Herramientas de cumplimiento ocultas al sindicato

**Files:**
- Create: `app/Modules/Tenancy/Support/ComplianceRoutes.php`, `app/Modules/Tenancy/Http/Middleware/LimitUnionToCompensation.php`
- Modify: `routes/company.php` (agrega el middleware al grupo), `resources/js/lib/app-navigation.ts` (`complianceNavItems` vacío si `tenant.type === 'union'`; entrada «Compensación» llega en Task 6), `TenantDashboardController` (sindicato → redirige a `tenant.compensation.index` cuando exista; mientras, a `tenant.employees.index`), `resources/js/lib/palette-commands.ts`
- Test: `tests/Feature/Tenancy/UnionToolsTest.php`

**Interfaces:**
- Produces: `ComplianceRoutes::patterns(): list<string>` = `['tenant.campaigns.*','tenant.telework.*','tenant.seating.*','tenant.assistant.*','tenant.work-centers.*']`; middleware: sindicato + ruta que coincide → `abort(404)`.

- [ ] **Step 1: Pruebas que fallan.** `it answers 404 to a union on every route matching ComplianceRoutes`, **por URL con ids reales de una campaña y un centro de otra cuenta** (Review Focus 2); `it lets a union reach employees, areas, members and the dashboard redirect`; `it leaves companies untouched`; **`it forces every named tenant.* route to be either compliance or allowed`** (recorre `Route::getRoutes()`; falla con el nombre de la ruta no clasificada; las permitidas: `tenant.dashboard`, `tenant.employees.*`, `tenant.areas.*`, `tenant.members.*`, `tenant.job-grades.*`, `tenant.compensation.*`); `it hides the compliance nav for a union`.
- [ ] **Step 2:** correr → FAIL. **Step 3: Implementar** (la lista es el registro: una herramienta nueva obliga a registrarla por la prueba). **Step 4:** pasa; `npm run types:check`.
- [ ] **Step 5: Commit** `"Calculadora: el sindicato no ve las herramientas de cumplimiento"`.

### Task 4: Relajar el padrón en la base

**Files:**
- Create: migración `2026_11_30_200000_relax_employees_for_unions.php`
- Modify: `Employee` (docblock y casts nullable), `CompanyRoster` (`whereNotNull` en `name`, `gender`, `work_center_id`, `area_id`: `RosterEntry` conserva sus tipos), `tests/Feature/Organization/EmployeeStorageTest.php`
- Test: `tests/Feature/Organization/EmployeeStorageTest.php` (cambios aprobados por el usuario), `tests/Feature/Organization/RelaxedRosterTest.php`

**Interfaces:**
- Produces: `employees` acepta `NULL` en `email`/`mobile`/`gender`/`work_center_id`/`area_id`/`name`/`job_title`; se quita `employees_contact_present`; `CHECK employees_identified` (`name IS NOT NULL OR employee_number IS NOT NULL`); `employee_number` único insensible a mayúsculas ya existe.

- [ ] **Step 1: Cambio de comportamiento explícito (decisión del usuario, spec «Padrón»).** Reemplazar en `EmployeeStorageTest` las afirmaciones de que la base rechaza contacto, género, centro, área o nombre ausentes por: `it requires a name or an employee number` (ambos nulos → falla `employees_identified`) y `it accepts a person with only an employee number`. Pruebas nuevas: `it leaves out people without a center, area, gender or name from the Roster contract`.
- [ ] **Step 2:** correr → FAIL. **Step 3: Implementar** migración (con `down()` que restaura las restricciones) y el filtro del roster. **Step 4:** pasa, incluida toda la carpeta `tests/Feature/Organization` (el formulario y el importador de empresas siguen exigiendo todo: se afirma en Task 5).
- [ ] **Step 5: Commit** `"Calculadora: el padrón acepta personas sin contacto, centro ni área"`.

### Task 5: Categorías y niveles, ingreso y grupo en el padrón

**Files:**
- Create: migraciones `create_job_grades_table` y `add_compensation_fields_to_employees` (`job_grade_id`, `hired_on date`, `staff_group`, `state`), `Organization/Models/JobGrade.php`, `Enums/StaffGroup.php`, `Contracts/JobGrades.php`, `Support/CompanyJobGrades.php`, `Actions/FindOrCreateJobGrade.php`, factory
- Modify: `EmployeeData`, `EmployeeAttributes`, `EmployeeRequest`, `EmployeeCsv::COLUMNS` (opcionales `categoria`, `nivel`, `fecha_de_ingreso`, `grupo`, `estado`), `PlanEmployeeImport`, `ImportEmployees`, `resources/js/components/organization/employee-*.tsx`, `lang/es/validation.php`
- Test: `tests/Feature/Organization/{JobGradeTest,EmployeeCompensationFieldsTest,EmployeeImportTest}.php`

**Interfaces:**
- Produces: `JobGrade(category, level)` único `(tenant_id, lower(category), lower(level))`, llave `(id, tenant_id)`; `enum StaffGroup: string { Unionized='unionized'; Trusted='trusted' }`; `Organization\Contracts\JobGrades { page(string $search, int $perPage): LengthAwarePaginator<int,JobGradeData>; find(string $id): ?JobGradeData; findMany(list<string> $ids): array<string,JobGradeData> }`. `state` se valida contra la lista de estados de los centros: **exponerla como `Tenancy\Contracts\WorkCenters::states(): array<string,string>`** (valor => etiqueta) si no existe.

- [ ] **Step 1: Pruebas que fallan.** `it creates a new category and level on import, like areas`; `it reads categoria, nivel, fecha_de_ingreso, grupo and estado when present and ignores them when absent` (una plantilla vieja sigue funcionando); `it rejects a bad date, an unknown group and an unknown state`; `it keeps requiring contact, gender, center and area for company accounts in the form and the import` (la defensa que reemplaza la de la base); `it keeps job grades apart per company` (A/B, URL 404); `it searches job grades without case or accents`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar** (mismo patrón que `FindOrCreateArea`/`Areas`). **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: categoría, nivel, ingreso y grupo en el padrón"`.

### Task 6: Padrón de sindicato

**Files:**
- Modify: `EmployeeRequest` (si la cuenta es sindicato: `employee_number` obligatorio; todo lo demás opcional; basta nombre o número), `EmployeeCsv` (en sindicato solo se exigen `numero_de_empleado` y las columnas que traiga), `PlanEmployeeImport`, `employee-form-*.tsx` y `employees-table.tsx` (se muestra «ID de la empresa» en lugar de nombre si falta), plantilla de importación según tipo
- Test: `tests/Feature/Organization/UnionRosterTest.php`

**Interfaces:**
- Consumes: `CurrentTenant` / `Companies` para saber el tipo (Task 2).
- Produces: el mismo `EmployeeData` con campos nulables que ya admiten las Tasks 4 y 5 (los tipos de `EmployeeData` pasan a `?`).

- [ ] **Step 1: Pruebas que fallan.** `it imports a union file with only ID, category, level, hired_on and group`; `it requires the company ID in a union and rejects a repeated one without caring about case`; `it shows a person by company ID when the name is missing`; `it serves a union template without company-only columns`; `it never creates employees without contact for a company` (regresión).
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** **Step 4:** pasa; `npm run types:check`.
- [ ] **Step 5: Commit** `"Calculadora: el sindicato carga su padrón con el ID de la empresa"`.

### Task 7: Módulo `Compensation`, permisos y privacidad

**Files:**
- Create: `Compensation/Providers/CompensationServiceProvider.php` (en `bootstrap/providers.php`), `routes/compensation.php` (incluido en `routes/company.php`), migración `add_compensation_permissions` (patrón de `add_seating_permissions`; `owner` y `hr`), `Http/Middleware/BlockWhileImpersonating.php`, página `resources/js/pages/compensation/index.tsx` (vacía con siguiente paso)
- Modify: `ResolveTenant` (banderas `view_compensation`, `manage_compensation`), `RedactSensitiveData` (+ llaves `salary`, `daily_salary`, `salary_cents`, `sbc`, `amount_cents`), `app-navigation.ts` (entrada «Compensación» para quien tiene `view_compensation`, sindicato incluido), `tests/Unit/ArchitectureTest.php` (reglas de `Compensation` como las de `Seating`), `tests/Feature/TenantScopedModelsTest.php`
- Test: `tests/Feature/Compensation/{PermissionsTest,ImpersonationTest,PrivacyTest}.php`, `tests/Unit/Logging/RedactSensitiveDataTest.php` (casos)

**Interfaces:**
- Produces: rutas `tenant.compensation.index`; banderas `can.view_compensation|manage_compensation`.

- [ ] **Step 1: Pruebas que fallan.** `it gives view and manage to owner and hr only` (viewer y committee: 403 y sin bandera); `it answers 404 during an impersonation`; `it redacts every salary key at any depth`; `it exposes no salary in the props of employees, dashboard or results pages` (`HrPagesPrivacyTest` se extiende); `it forbids adding salary or category/level as filters of survey results`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** **Step 4:** pasa, `ArchitectureTest`.
- [ ] **Step 5: Commit** `"Calculadora: módulo Compensación con sus permisos"`.

### Task 8: Dinero y parámetros fiscales

**Files:**
- Create: migración `create_fiscal_parameters_table`, `Compensation/Models/FiscalParameter.php`, `Support/Calculation/Money.php`, `Support/FiscalParameters.php`, seeder `database/seeders/FiscalParametersSeeder.php` (invocado por una migración de datos, versionado), factory
- Test: `tests/Unit/Compensation/MoneyTest.php`, `tests/Feature/Compensation/FiscalParametersTest.php`

**Interfaces:**
- Produces: `Money::cents(int|string $pesos): int`, `Money::truncate(int $numerator, int $denominator): int`, `Money::round(int $numerator, int $denominator): int` (mitad hacia arriba), `Money::format(int $cents): string`. `FiscalParameter(key, state ?string, valid_from, valid_to ?, value_int, scale, source, consulted_on)`. `FiscalParameters::at(CarbonImmutable $date): FiscalSnapshot` con `uma(): int`, `minimumWage(): int`, `sbcCap(): int`, `rate(string $key): Rate` (`Rate(numerator,denominator)`), `payrollTax(string $state): Rate`, `isStale(): bool`, `staleKeys(): list<string>`; más allá de la última vigencia arrastra el último valor y lo marca.

- [ ] **Step 1: Pruebas que fallan.** `it truncates 524.6575 to 524.65 and rounds half up elsewhere`; `it never uses floats` (aserción sobre el código: `Support/Calculation` no contiene `float`/`round(`/`/ ` sobre `int` salvo `intdiv`); `it returns the 2026 values of criterios.md`; `it carries the last value past its end and flags it stale`; `it knows payroll tax for the 32 states`. `mutates(Money::class, FiscalParameters::class)`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar**; el seeder toma **solo** los valores de `criterios.md`. **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: dinero en centavos y parámetros fiscales con fuente"`.

### Task 9: Catálogo de prestaciones y términos del contrato

**Files:**
- Create: migraciones `create_benefits_table`, `create_contract_terms_table`; `Models/{Benefit,ContractTerm}.php`; `Enums/{BenefitKind,Frequency,SbcIntegration}.php`; `Support/BaseCatalog.php`; `Actions/{SaveBenefit,SaveContractTerm}.php`; `Http/Controllers/BenefitController.php`; `Http/Requests/BenefitRequest.php`; páginas `compensation/benefits.tsx`
- Test: `tests/Feature/Compensation/{BenefitTest,BenefitIsolationTest}.php`

**Interfaces:**
- Produces: `Benefit` con `name`, `is_base`, `kind` (`salary_days|salary_percent|fixed_amount|uma_multiple`), `amount`, `frequency` (`daily|monthly|yearly`), `applies_to_group ?StaffGroup`, `job_grade_ids` (json), `min_tenure_months`, `tenure_table` (json: `[{from_years,value}]`), `sbc_integration` (`always|never|up_to_cap`), `sbc_cap_cents ?`, `in_payroll_tax`, `in_ptu_base`, `in_seniority_base`, `income_tax_exempt` (solo se señala), `cap_cents ?`. `ContractTerm(benefit_id, effective_from, value, tenure_table ?)`. `BaseCatalog::ensure()` crea los diez del spec (aguinaldo, vacaciones, prima vacacional, fondo de ahorro, vales de despensa, prima de antigüedad, prima dominical, bonos de productividad y de asistencia, gastos médicos mayores, fondo de jubilación) una sola vez. La prima de riesgo de trabajo es un `ContractTerm` especial por cuenta y vigencia (decisión 9).

- [ ] **Step 1: Pruebas que fallan.** `it seeds the ten base benefits once per account`; `it creates a custom benefit from attributes only` (formulario, sin código); `it rejects a negative amount, an unknown kind and a cap below zero`; `it keeps accounts apart` (404 por URL); `it needs manage-compensation to write and view-compensation to read`; logs `compensation.benefit_saved` sin montos en el texto.
- [ ] **Step 2:** FAIL. **Step 3: Implementar** con la pantalla de catálogo partiendo de la tabla de `miembros`. **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: catálogo de prestaciones y términos del contrato"`.

### Task 10: Tabulador y sueldo individual

**Files:**
- Create: migraciones `create_grade_salaries_table`, `create_salary_overrides_table`; modelos `GradeSalary`, `SalaryOverride`; `Support/{TabulatorCsv,OverridesCsv}.php`; `Actions/{SaveGradeSalary,ImportTabulator,SaveSalaryOverride,ImportSalaryOverrides}.php`; controladores y páginas `compensation/{tabulator,overrides}.tsx`
- Test: `tests/Feature/Compensation/{TabulatorTest,SalaryOverrideTest,TabulatorImportTest}.php`

**Interfaces:**
- Consumes: `Organization\Contracts\JobGrades` (Task 5) y `Organization\Contracts\CompensationRoster::findByNumbers` (Task 11, se agrega aquí si hace falta).
- Produces: `GradeSalary(job_grade_id, daily_salary_cents, effective_from)` único por `(job_grade_id, effective_from)`; `SalaryOverride(employee_id, daily_salary_cents, effective_from, note)` — una vigente por persona al día; CSV de tabulador (`categoria,nivel,salario_diario,vigencia`) y de sueldos individuales (`id_de_la_empresa,salario_diario,vigencia`), ambos transaccionales (todo o nada), máx. 2 000 filas.

- [ ] **Step 1: Pruebas que fallan.** `it lists the tabulator paginated by category and level with server-side search`; `it rejects a salary of zero or negative and a repeated category-level-date`; `it replaces an override with another valid from a later date and keeps the history`; **`it rejects an override whose company ID does not exist, is deactivated or is repeated in the file`** y no escribe nada (Review Focus 1); `it keeps two accounts' tabulators apart`; `it writes tabulator_imported and override_saved logs and audit with counts only, never salaries`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar** (importador: `categoria`/`nivel` se crean si faltan, como en Task 5). **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: tabulador y sueldo individual"`.

### Task 11: Contrato del padrón para Compensación y factor de integración

**Files:**
- Create: `Organization/Contracts/CompensationRoster.php`, `Data/{HeadcountCell,CompensationPerson,RosterGaps}.php`, `Support/CompanyCompensationRoster.php` (bind en `OrganizationServiceProvider`), `Compensation/Support/Calculation/{IntegrationFactor,SalaryBase}.php`
- Test: `tests/Feature/Organization/CompensationRosterTest.php`, `tests/Unit/Compensation/{IntegrationFactorTest,SalaryBaseTest}.php`

**Interfaces:**
- Produces: `CompensationRoster::cells(CarbonImmutable $asOf, list<string> $excludingNumbers): list<HeadcountCell>` con `HeadcountCell(string $jobGradeId, ?string $areaId, StaffGroup $group, ?string $state, CarbonImmutable $hireMonth, int $count)` (agrupa por mes de ingreso para fijar el aniversario; **nunca hidrata personas**); `individuals(list<string> $numbers): array<string,CompensationPerson>` (`employeeNumber, jobGradeId, areaId, group, state, hiredOn`; sin nombre, contacto ni género); `gaps(): RosterGaps(total, costed, missing: list<string> /* employee_number */, inactive)`. `IntegrationFactor::for(int $tenureYears, FactorTerms $terms): Rate` = `1 + aguinaldo/365 + vacaciones × prima/365`; `SalaryBase::sbc(int $dailySalaryCents, Rate $factor, int $sbcCapCents): int` (trunca; aplica el tope de 25 UMA). `LegalVacationDays::forYears(int $years): int` (tabla LFT art. 76). Años de servicio completos al día `asOf` por aniversario exacto.
- Consume: Task 5, 8.

- [ ] **Step 1: Pruebas que fallan.** Unit: `it gives factor 1.0493 with the legal minimums`; `it gives SBC 52465 cents for 500 pesos with 15 days, 12 vacation days and 25 percent`; `it caps SBC at 25 UMA and one cent above stays at the cap`; **`it steps vacation days on the exact anniversary, for a person hired on Feb 29 and on the scenario date, and with 0 years`** (Review Focus 4). Feature: `it counts by cell without loading employees` (`expectsDatabaseQueryCount`); `it leaves overrides out of the cells`; `it reports who lacks grade or hire date by company ID, never by name or id`; `it ignores deactivated people`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar** (una consulta `GROUP BY`). `mutates(IntegrationFactor::class, SalaryBase::class)`. **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: padrón agrupado y salario base de cotización"`.

### Task 12: Cargas patronales

**Files:**
- Create: `Compensation/Support/Calculation/EmployerContributions.php`, `Data/ContributionLine.php`
- Test: `tests/Unit/Compensation/EmployerContributionsTest.php`

**Interfaces:**
- Produces: `EmployerContributions::forPerson(int $sbcCents, int $days, FiscalSnapshot $fiscal, ?Rate $workRiskPremium, ?string $state, int $payrollTaxableCents): list<ContributionLine>` con líneas `imss_fixed, imss_surplus, imss_pensioners, imss_cash, imss_disability_life, work_risk, imss_childcare, retirement, severance_old_age, infonavit, payroll_tax` (`ContributionLine(key, cents, calculated bool)`). Sin prima de riesgo → `work_risk` con `calculated=false` y 0; sin estado → `payroll_tax` igual.

- [ ] **Step 1: Pruebas que fallan.** Un caso trabajado por línea con los valores de `criterios.md` (SBC de $524.65 y de $3,000 que supera 3 UMA para el excedente; rango de cesantía y vejez en dos años distintos); `it marks work_risk and payroll_tax as not calculated without premium or state`; `it applies the cap and the SBC floor at the minimum wage`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar** tomando solo tasas del `FiscalSnapshot`. `mutates(EmployerContributions::class)`. **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: cuotas del IMSS, Infonavit e impuesto sobre nómina"`.

### Task 13: Costo de un escenario (primer año)

**Files:**
- Create: `Support/Calculation/{BenefitCost,ScenarioCost}.php`, `Data/{ScenarioInput,ScenarioChange,CostBreakdown,MonthlyCost}.php`
- Test: `tests/Unit/Compensation/{BenefitCostTest,ScenarioCostTest}.php`

**Interfaces:**
- Consumes: celdas e individuales (Task 11), tabulador y sueldos (Task 10), términos (Task 9), `EmployerContributions` (Task 12).
- Produces: `ScenarioCost::firstYear(ScenarioInput $in): CostBreakdown` con costo **antes** y **después**, delta por concepto (salario, cada prestación, cada línea de cargas), costo recurrente, costo del año con retroactividad, mes a mes del primer año, por área/categoría/grupo, y `costed: RosterGaps`. `ScenarioInput` lleva fecha de vigencia, retroactividad en meses, % de salario por grupo (confianza copia sindicalizado por omisión), cambios a términos, estado por omisión y la fecha de cálculo. `BenefitCost::annual(Benefit, term, salaryCents, tenureYears, ...)`: días de salario, % del salario, monto fijo por periodo, múltiplo de UMA, con tope y tabla por antigüedad; vales de despensa integran solo hasta 40 % de la UMA.
- Un aumento porcentual se aplica también al sueldo individual de quien lo tiene.

- [ ] **Step 1: Pruebas que fallan.** `it costs a 5 percent salary increase with every drag-along concept` (aguinaldo, vacaciones, prima, fondo, IMSS, Infonavit, ISN en la cascada); `it costs zero for an unchanged scenario and for zero retroactivity`; `it costs a new benefit differently from a salary point`; `it caps vales at 40 percent of UMA and one peso above`; **`it applies the percent to an override above and below the tabulator`**; `it excludes people without grade or hire date and reports them`; `it handles an empty roster and a one-person group`; `it adds the cells to the total with exact sums` (la suma por mes igual al anual). `mutates(BenefitCost::class, ScenarioCost::class)`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: costo de un escenario el primer año"`.

### Task 14: Largo plazo, prima de antigüedad y sensibilidad

**Files:**
- Create: `Support/Calculation/{Projection,SeniorityPremium,Sensitivity}.php`, migración `create_assumptions_table`, `Models/Assumption.php`, `Actions/SaveAssumption.php`, `Data/{ProjectionYear,Assumptions}.php`
- Test: `tests/Unit/Compensation/{ProjectionTest,SeniorityPremiumTest,SensitivityTest}.php`, `tests/Feature/Compensation/AssumptionsTest.php`

**Interfaces:**
- Produces: `Assumptions(inflation, turnover, headcountGrowth, futureRevisions, followingYearsRate)` en puntos base, con fuente y fecha, editables por escenario (valores por defecto en `Assumption`); `Projection::years(ScenarioInput, Assumptions, int $horizon /*3..5*/): list<ProjectionYear>` (la antigüedad sube sola; cada año visible; las tasas patronales cambian por año); `SeniorityPremium::estimate(...)`: `12 días × años × min(salario diario, 2 salarios mínimos)` antes y después, solo para quien cumple las condiciones de ley; `Sensitivity::around(...)`: costo del punto porcentual y de ±1 punto.

- [ ] **Step 1: Pruebas que fallan.** `it estimates the seniority premium from 12,000,000 to 12,600,000 cents with +5 percent and 20 years`; `it caps the premium base at two minimum wages`; `it grows tenure by itself and shows each year`; `it rejects a horizon of 2 or 6`; `it flags a stale fiscal value in the projection`; `it returns the cost of one percentage point and of plus and minus one point`; `it keeps assumptions with source and date per scenario`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** `mutates(Projection::class, SeniorityPremium::class, Sensitivity::class)`. **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: proyección, prima de antigüedad y sensibilidad"`.

### Task 15: Escenarios y su editor

**Files:**
- Create: migración `create_scenarios_table`, `Models/Scenario.php`, `Actions/{SaveScenario,DuplicateScenario,ArchiveScenario}.php`, `Http/Controllers/ScenarioController.php`, `Http/Requests/ScenarioRequest.php`, páginas `compensation/{scenarios,scenario}.tsx`, `lib/compensation/scenario-form.ts` (+ prueba Vitest)
- Test: `tests/Feature/Compensation/{ScenarioTest,ScenarioIsolationTest}.php`

**Interfaces:**
- Produces: `Scenario(name, effective_on, retroactive_months, horizon_years, group_changes json, term_changes json, assumptions json, default_state ?)`; lista paginada con costo del primer año y comparación entre dos o más escenarios (A contra B por concepto). Nunca se borra: se archiva.

- [ ] **Step 1: Pruebas que fallan.** `it saves a scenario with changes per group and per benefit`; `it copies the unionized raise to trusted by default`; `it rejects retroactivity longer than the horizon and an effective date before the base-year terms`; `it duplicates and archives`; `it lists paginated`; `it compares two scenarios`; `it keeps accounts apart` (404); logs `compensation.scenario_saved` con ids y sin porcentajes ni montos.
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** **Step 4:** pasa; `npm run test:js`.
- [ ] **Step 5: Commit** `"Calculadora: escenarios, editor y comparación"`.

### Task 16: Reporte interno

**Files:**
- Create: `Support/ReportBuilder.php`, `Http/Controllers/ReportController.php`, páginas `compensation/report.tsx` + componentes (`print-button` ya existe en Results: reutilizar su patrón), `Actions/ExportPersonAnnex.php`
- Test: `tests/Feature/Compensation/{ReportTest,ReportPrivacyTest,AnnexTest}.php`

**Interfaces:**
- Produces: página con las secciones del objetivo 4: abre con «X de Y personas costeadas» y la lista de faltantes por ID de la empresa; costo del primer año con retroactividad y recurrente; cascada por concepto; cortes por área, categoría y grupo; mes a mes; proyección; prima de antigüedad; costo del punto; sensibilidad; comparación; tabla de supuestos con vigencia; aviso de parámetros vencidos; aviso de sueldos individuales (cuántos, %, marca en el anexo; aviso si pasan de la décima parte); aviso «no sustituye una valuación actuarial (NIF D-3) ni es asesoría legal o fiscal». Vista del sindicato: valor bruto para la persona contra costo para la empresa por concepto (las exentas solo se señalan). Anexo por persona solo con `manage-compensation`, con ID de la empresa; ver y exportar van a `RecordAudit` (`compensation.report_viewed`, `compensation.annex_exported`).
- Consume: Tasks 11 a 15; consultas acotadas (`expectsDatabaseQueryCount`).

- [ ] **Step 1: Pruebas que fallan.** `it shows every section with its assumptions`; `it opens with people costed of total and lists the missing by company ID`; `it shows the worker-gross-versus-employer-cost table`; `it warns above one tenth of the headcount with individual salaries`; `it audits viewing and exporting without salaries in the metadata`; `it never includes the internal id in the annex`; `it hides the annex without manage-compensation`; props sin nombres, contactos ni género; consultas acotadas.
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** **Step 4:** pasa; revisión visual en claro, oscuro y 375 px contra la pantalla de referencia.
- [ ] **Step 5: Commit** `"Calculadora: reporte de impacto a corto y largo plazo"`.

### Task 17: Enlace compartible con grupos pequeños suprimidos

**Files:**
- Create: `Results/Contracts/SmallGroups.php` (+ binding a `GroupMinimum`), migración `create_compensation_report_shares_table`, `Models/ReportShare.php`, `Support/SharedGroups.php`, `Actions/{CreateReportShare,RevokeReportShare}.php`, `Http/Controllers/SharedReportController.php`, `routes/compensation-shared.php` (público, `throttle`, nombre `compensation.report.shared`; entra a la lista pública de `RouteSecurityTest` con su razón), página `compensation/shared-report.tsx`
- Test: `tests/Unit/Compensation/SharedGroupsTest.php`, `tests/Feature/Compensation/SharedReportTest.php`

**Interfaces:**
- Produces: `SmallGroups::visibleKeys(array<string,int> $headcountByGroup): list<string>`; `SharedGroups::apply(CostBreakdown, SmallGroups): CostBreakdown` agrupa los pequeños en «Otros» y, si «Otros» quedara bajo el mínimo, le suma el siguiente grupo más chico; **los totales son exactos**; el enlace omite el anexo y el mensual por grupo. Enlace con hash del token, expira como máximo a los 7 días, revocable, abre por `Companies::enter`; token desconocido/vencido/revocado/cuenta bloqueada → mismo 404; cada apertura va a la bitácora.

- [ ] **Step 1: Pruebas que fallan.** `it folds groups under the minimum into Others`; **`it adds the next smallest group when Others is still under the minimum, so subtracting subtotals from the total reveals nothing`**; `it keeps exact totals`; `it hides everything but the total when only one group would be visible` (Review Focus 3); `it leaves out the annex and the monthly-by-group`; `it answers the same 404 for unknown, expired, revoked and blocked`; `it audits each opening`; `it throttles`.
- [ ] **Step 2:** FAIL. **Step 3: Implementar.** `mutates(SharedGroups::class)`. **Step 4:** pasa.
- [ ] **Step 5: Commit** `"Calculadora: enlace de solo lectura con grupos pequeños agrupados"`.

### Task 18: Flujo completo, navegador y cierre

**Files:**
- Create: `tests/Browser/CompensationTest.php`
- Modify: `docs/testing.md` (módulo y umbrales si lista módulos), `.env.example` si hubo configuración nueva, `tests/Feature/PaginatedListsTest.php` y `tests/Unit/PaginatedListsTest.php` (listas nuevas), `tests/Unit/BrowserCoverageTest.php` si aplica

- [ ] **Step 1: Pruebas de navegador.** `visitPage()` de cada página nueva (componente exacto, sin errores de JS, sin violaciones de CSP, axe); flujo principal para una cuenta de sindicato: importar padrón → importar tabulador → armar un escenario → ver el reporte → compartirlo → abrir el enlace sin sesión.
- [ ] **Step 2:** correr `composer test:browser` (requiere `npm run build`) → ajustar la UI hasta pasar.
- [ ] **Step 3:** `composer ci:check` completo en verde (nunca al mismo tiempo que otra suite sobre la misma base).
- [ ] **Step 4:** revisión visual de las pantallas nuevas en claro, oscuro y 375 px.
- [ ] **Step 5: Commit** `"Calculadora: flujo completo del sindicato en navegador"`. Sin push; decir al usuario que está lista y preguntar «¿Creo el PR?».

---

## Self-review (hecha)

- **Cobertura del spec:** tipos de cuenta (T2, T3), relajar padrón y dos identificadores (T4–T6), `job_grades`/ingreso/grupo (T5), módulo y permisos/privacidad/suplantación (T7), parámetros fiscales (T1, T8), prestaciones (T9), tabulador y sueldo individual (T10), cálculo (T11–T14), escenarios (T15), reporte interno, vista del sindicato y aviso actuarial (T16), enlace compartible con «Otros» y supresión complementaria (T17), pruebas de navegador (T18). Decisiones 8–14 repartidas en T5, T9, T11, T13, T16.
- **Consistencia de nombres:** `HeadcountCell`, `CompensationPerson`, `RosterGaps`, `FiscalSnapshot`, `Rate`, `CostBreakdown`, `ScenarioInput`, `SmallGroups` se definen en la tarea que los produce y se consumen después con la misma firma.
- **Decisiones aprobadas por el usuario el 2026-11-03:** (1) regla de redondeo del SBC (truncar), marcada en Global Constraints; (2) agrupar por **mes de ingreso** en vez de por «años de servicio» para fijar el aniversario exacto: es un refinamiento del spec, mismo contrato público; (3) el asistente Humi se oculta al sindicato junto con las herramientas de cumplimiento; (4) caducidad máxima de 7 días del enlace compartible.
