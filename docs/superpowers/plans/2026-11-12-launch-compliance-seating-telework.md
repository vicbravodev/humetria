# F2 · Veracidad del cumplimiento en Ley Silla y NOM-037 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que «sin registros» nunca cuente como «Cumple» ni como «Hecho» en Ley Silla y NOM-037, y que la Comisión vea lo que de verdad le toca.

**Architecture:** Cada módulo ya tiene una fuente única de estado por renglón (`Seating\Support\ComplianceRows`, `Telework\Support\ComplianceRows`) que consumen el tablero del centro, la ruta, la inspección, el expediente, el Panel y Humi. El arreglo va en esa fuente: Seating gana el estado `Pending` («Sin registros»), que queda fuera del medidor y de «Lo puedes mostrar»; Telework deja de contar etapas como hechas cuando nadie está dado de alta. Los consumidores solo aprenden a pintar el estado nuevo.

**Tech Stack:** Laravel 13, Pest, Inertia + React. Sin migraciones ni dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (C-01, C-02, C-06, C-07, C-08, C-10, C-11, C-12, C-16, C-18, C-20, C-21) · **Mandan:** `docs/normas/ley-silla/criterios.md` (C-03, C-18), `docs/normas/nom-037/criterios.md` (C-04, C-07, C-11, C-12, C-24) · Specs: `2026-11-02-ley-silla-design.md` («Qué cuenta como cumplido»), `2026-10-30-nom-037-design.md`.

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario. PHPStan `max` y TS strictest sin supresiones ni `as`.
- Regla del frente: **un renglón que no tiene sobre qué evaluarse es `Pending` o `NotApplicable`, nunca `Met`/`Done`.** Ningún medidor, etapa, lista «Lo puedes mostrar» ni expediente «Solo lo que tienes» lo incluye.
- Humetria no califica: los renglones son conteos de lo registrado (anti-objetivo «calificación automática» de ambos diseños). No se agregan veredictos.
- Seating y Telework no importan internos uno del otro: solo `Contracts` y `Data` (`Seating\Contracts\CommitteeWork`, `Telework\Contracts\Committee`).
- El panel tutea; el portal y la encuesta hablan de «usted». Textos en es-MX.
- Este frente es el único que toca `resources/js/components/humetria/inspection-*.tsx` y `lib/route-stages`.
- No toca `Telework\Actions\OpenPortal`, `PortalController` ni `components/telework/portal/` (son de F3).
- Pantallas de referencia: `docs/rediseno/pantallas/png/V2-Ruta-Silla.png`, `V2-Ruta-N037.png`, `V3-Inspeccion.png`, `V2-Comision.png`, `LS-Puesto.png`, `N037-Revision.png`. Revisar en claro, oscuro y 375 px.
- Rama `fix/launch-compliance-seating-telework`.

## Review Focus

1. **Centro con puestos de pie y cero evaluaciones.** El tablero no dice «9 de 13 cumplen»: R3, R7, R8 y R9 salen «Sin registros», y la etapa 4 de la ruta no está hecha. → Task 1, Task 2.
2. **Centro donde todos los puestos quedaron en nivel A de la Figura 1.** R5 y R11 no pasan a «Sin registros»: hay registros y no exigen medidas. → Task 1.
3. **Centro NOM-037 con solo aspirantes.** Las etapas 3 y 4 no dicen «Hecho»; al pasar el primer aspirante al padrón sin lugar validado, tampoco. → Task 3.
4. **Expediente «Solo lo que tienes» con todo en «Sin registros».** Sale vacío con su estado vacío y siguiente paso, no una hoja en blanco. → Task 2.
5. **Integrante de la Comisión asignado a un centro sin lista publicada y a otro con todo al día.** Su bandeja muestra la tarea del primero y nada del segundo; no ve centros que no son suyos. → Task 4.

---

### Task 1: Ley Silla: «Sin registros» es un estado (C-01)

**Files:**
- Modify: `app/Modules/Seating/Enums/RowState.php`, `app/Modules/Seating/Support/ComplianceRows.php:93-98,116-129,134-168,184-187,242-249`
- Test: `tests/Feature/Seating/ComplianceRowsTest.php`

**Interfaces:**
- Produces: `RowState::Pending = 'pending'`, `label()` → «Sin registros».
- Produces: `ComplianceRows::row()` gana un parámetro `bool $nothingToCheck = false`; cuando es `true` devuelve `new ComplianceRow($key, RowState::Pending)` antes de mirar `$missing`.
- Cuándo es `Pending`: R3, R7, R8, R9 sin ninguna evaluación firmada en el centro; R5 y R11 sin ningún puesto con colocación en la Figura 1; R6 sin ningún asiento registrado. R13 no cambia.

- [ ] **Step 1: Tests que fallan** (el `beforeEach` existente arma un centro que cumple; cada caso quita una cosa):
  - `it('does not call met what has no assessment to look at')`: centro con un puesto y una persona, sin `assess()` → `r3`, `r7`, `r8`, `r9` son `Pending`; `r2` es `Missing`.
  - `it('keeps them met once an assessment is signed')` → vuelven a `Met`.
  - `it('waits for a Figura 1 placement before judging measures and pauses')`: puesto sin colocación → `r5`, `r11` `Pending`; puesto en nivel A → `Met`.
  - `it('waits for a seat before judging seats')`: sin asientos → `r6` `Pending`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Seating/ComplianceRowsTest.php` → fallan: los renglones salen `Met`.
- [ ] **Step 3:** Implementar el caso del enum y la rama en `row()`; cada renglón afectado pasa su condición de «nada que revisar».
- [ ] **Step 4:** Verde. Pint y PHPStan en los dos archivos.
- [ ] **Step 5: Commit** `Ley Silla: un requisito sin registros queda «Sin registros», no «Cumple»`.

### Task 2: Nadie cuenta «Sin registros» como cumplido (C-01)

**Files:**
- Modify: `app/Modules/Seating/Support/CenterBoard.php` (`summary` gana `pending`), `RoutePages.php:88-95` (inspección), `Dossier.php:56-68`, `PlanPages.php:41`, `SeatingPages.php:75,120`, `CompanySeatingRoute.php`
- Modify: `resources/js/lib/seating/requirements.ts:46`, `resources/js/components/seating/requirements-summary.tsx:17-23`, `requirement-row.tsx`, `resources/js/pages/seating/inspection.tsx:63`, `components/humetria/inspection-item.tsx:15`, tipos de Seating
- Test: `tests/Feature/Seating/CenterPageTest.php`, `InspectionPageTest.php`, `DossierTest.php`, `RoutePageTest.php`, `tests/Unit/Seating/RouteStagesTest.php`, `tests/js/lib/seating/requirements.test.ts`, `tests/Browser/SeatingTest.php`

- [ ] **Step 1: Tests que fallan**, todos con el centro «puestos y cero evaluaciones»:
  - `CenterPageTest`: `summary` trae `met`, `warning`, `missing`, `pending`, y `met` no incluye los `Pending`.
  - `InspectionPageTest`: los renglones `Pending` están en «Te falta», con su detalle «Aún no hay evaluaciones firmadas» (o «…puestos clasificados», «…asientos registrados»), y **no** en «Lo puedes mostrar».
  - `DossierTest`: con `?ready=1` no salen.
  - `RouteStagesTest`: la etapa 4 no está hecha con `r7`/`r11` en `Pending`.
  - `requirements.test.ts`: el resumen dice «5 de 13 cumplen · 6 sin registros».
- [ ] **Step 2:** Correr → fallan.
- [ ] **Step 3:** Implementar backend, luego frontend. `inspection-item.tsx` pinta `pending` con el tono neutro del design system (el mismo de «No aplica» en Telework), nunca con el de cumplido. El medidor de arco usa solo `met` sobre 13.
- [ ] **Step 4:** Verde. `ListPendingActions` (Assistant) y el Panel leen `CompanySeatingRoute`: correr `tests/Feature/Assistant` y `tests/Feature/Tenancy/DashboardTest.php` filtrados para confirmar que no cambian de forma inesperada.
- [ ] **Step 5:** Navegador: centro sin evaluaciones en claro, oscuro y 375 px contra `V2-Ruta-Silla.png` y `V3-Inspeccion.png`.
- [ ] **Step 6: Commit** `Ley Silla: el medidor, la inspección y el expediente dejan fuera lo que no tiene registros`.

### Task 3: NOM-037: sin personas en el padrón no hay etapas hechas (C-02)

**Files:**
- Modify: `app/Modules/Telework/Support/RouteStages.php:58-76`
- Test: `tests/Unit/Telework/RouteStagesTest.php`, `tests/Feature/Telework/RouteTest.php`, `RoutePageTest.php`

**Interfaces:**
- Produces: dentro de `RouteStages::from`, `$unregistered` = el renglón `5.1` no está `Done`. Las etapas de índice 1, 2 y 3 quedan `false` cuando `$unregistered`. El retorno temprano de «nadie teletrabaja» sigue usando `$nobody` (5.1 `NotApplicable`) sin cambios.

- [ ] **Step 1: Tests que fallan** en `RouteStagesTest.php`:
  - solo aspirantes (5.1 `Missing`, 5.1.1/5.5/5.7/5.9 `NotApplicable`, lista publicada) → etapas 3 y 4 **no** hechas;
  - una persona activa con lugar validado, equipo y capacitación → hechas;
  - una persona activa sin lugar validado → etapa 3 no hecha.
- [ ] **Step 2:** Correr → falla el primero (hoy salen hechas).
- [ ] **Step 3:** Implementar. El docblock de la clase ya describe este comportamiento; el código lo alcanza.
- [ ] **Step 4:** Verde, más `tests/Feature/Telework/ComplianceRowsTest.php` e `InspectionPageTest.php` sin cambios.
- [ ] **Step 5: Commit** `NOM-037: la ruta no da por hechos el lugar, el equipo ni la capacitación mientras no hay nadie en el padrón`.

### Task 4: La bandeja de la Comisión muestra lo que la bloquea (C-11, C-12)

**Files:**
- Modify: `app/Modules/Telework/Support/TeleworkPages.php:139-178` (`queue()`), `app/Modules/Seating/Support/CompanyCommitteeWork.php:17-37`, `app/Modules/Seating/Contracts/CommitteeWork.php` y su DTO de tarea
- Modify: `resources/js/lib/telework/queue.ts`, `resources/js/pages/telework/queue.tsx`
- Test: `tests/Feature/Telework/CommitteeQueueTest.php`, `tests/Feature/Seating/CommitteeWorkTest.php`, `tests/js/lib/telework/queue.test.ts`, `tests/Browser/CommitteeQueueTest.php`

**Interfaces:**
- Produces: dos tipos de tarea nuevos. `checklist`: «El centro {nombre} no tiene lista de verificación publicada» (renglón `5.4 y 7` en `Missing`), enlace a la lista. `unassessed`: «{n} personas de pie sin evaluación en {centro}» (conteo de R2 en `Missing`), enlace a Evaluaciones.
- Solo para los centros asignados al integrante.

- [ ] **Step 1: Tests que fallan.** Integrante asignado a un centro sin lista publicada → su bandeja trae una tarea `checklist`; con la lista publicada desaparece. Centro con 3 personas de pie sin evaluar → tarea `unassessed` con `count === 3`. Integrante de otro centro no las ve. Centro al día → «Nada te espera».
- [ ] **Step 2–4:** Falla → implementar leyendo `ComplianceRows` de cada módulo para esos centros (sin consultas nuevas por tarea: una lectura por centro) → verde.
- [ ] **Step 5:** `queue.ts` da título y enlace a los dos tipos. Navegador: bandeja en 375 px contra `V2-Comision.png`.
- [ ] **Step 6: Commit** `Comisión: la bandeja avisa de la lista sin publicar y de las personas sin evaluar`.

### Task 5: NOM-037: estados y textos que se contradicen (C-06, C-07, C-08, C-20)

Un commit por comportamiento.

**C-06 · Validar con respuestas desfavorables exige observación**
- Modify: `app/Modules/Telework/Actions/ReviewWorkplace.php:110-119`, formulario de revisión en `resources/js/components/telework/`
- Test: `tests/Feature/Telework/ReviewPagesTest.php` y el test de `ReviewWorkplace`
- [ ] Test: validar una lista con 17 respuestas desfavorables y sin notas → error «Escribe una observación: la lista tiene 17 respuestas desfavorables.»; con notas → valida; sin desfavorables y sin notas → valida (C-12: cada resultado se registra «con fecha, nombre y observaciones»; C-11: la decisión es de la Comisión).
- [ ] Falla → implementar → verde → commit `NOM-037: validar un lugar con respuestas desfavorables pide la observación de la Comisión`.

**C-07 · Un mismo estado en el padrón y en el portal**
- Modify: `app/Modules/Telework/Http/Controllers/TeleworkerController.php:230-240` (`label()`), con la misma búsqueda de `WorkplaceReview` que `Support/PortalPending.php:52-55` (cargada con `with()`)
- Test: el test de la página del teletrabajador en `tests/Feature/Telework/`
- [ ] Test: lista enviada y revisada → etiqueta «Revisada por la Comisión», no «En revisión». La página no agrega consultas por persona.
- [ ] Commit `NOM-037: la ficha dice «Revisada por la Comisión» cuando la lista ya se revisó`.

**C-08 · «Sin capacitación · Al corriente»**
- Modify: `resources/js/components/telework/training-person-row.tsx:22-30`, `lib/telework/training.ts`
- Test: `tests/js/lib/telework/training.test.ts`
- [ ] Test: `lastTrainingOn === null` y dentro de plazo → «Aún sin capacitación · tiene hasta el {dueOn}». La lógica no cambia (criterio C-24).
- [ ] Commit `NOM-037: quien aún no se capacita ve su fecha límite, no «Al corriente»`.

**C-20 · «0 de 1 persona confirmó»**
- Modify: `app/Modules/Telework/Support/ComplianceRows.php:519` (`policy()`)
- Test: `tests/Feature/Telework/ComplianceRowsTest.php`
- [ ] Test: política publicada y nadie a quien pedirle confirmación → detalle «Aún no hay a quién pedirle que confirme». Con 1 de 3 → «1 de 3 personas confirmaron».
- [ ] Commit `NOM-037: el renglón de la política no cuenta personas que no existen`.

### Task 6: Ley Silla y padrón: fricciones (C-10, C-16, C-18, C-21)

Un commit por comportamiento.

| Hallazgo | Archivos | Cambio | Test |
|---|---|---|---|
| C-10 | `components/tenancy/applies-seating.tsx:41`, `onboarding-center.tsx:54`, `pages/seating/certificate.tsx:50` | «¿Alguien hace su trabajo de pie, aunque sea parte de su jornada?» (Disposiciones: «durante su jornada laboral»; criterio C-03) | `tests/Browser/ApplicabilityTest.php`, `tests/Feature/Seating/CertificateTest.php` si afirma el texto |
| C-16 | `components/seating/position-wizard-footer.tsx:48-53`, `position-wizard.tsx`, `lib/seating/position-steps.ts` | `missingInStep(step, values): string[]` con las listas `FIGURE_ONE` y `AREA` que ya existen; «Siguiente» no avanza y marca los campos | `tests/js/lib/seating/position-steps.test.ts`, navegador del asistente de puesto |
| C-18 | `components/seating/assessments-table.tsx:80`, `assessment-result.tsx:18`, `portal-acknowledgment.tsx:30`, `regulation-progress.tsx:15` | Envolver la fecha con `describeDate()` de `@/lib/calendar-date` | `tests/Browser/SeatingTest.php` (no aparece `2026-11-` en la tabla) |
| C-21 | `components/telework/roster-fields.tsx:68`, props del diálogo en `TeleworkerController` | El domicilio parte del primer lugar validado; casilla «Es el mismo que su lugar de trabajo» (C-04 pide los dos datos por separado: el campo se queda) | `tests/js/lib/telework/roster.test.ts`, test de la página del teletrabajador |

- [ ] Por cada fila: test que falla → cambio → verde → commit.

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Navegador: centro Ley Silla sin evaluaciones, centro NOM-037 con solo aspirantes, bandeja de la Comisión; claro, oscuro, 375 px.
- [ ] Preguntar «¿Creo el PR?».
