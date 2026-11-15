# F1 · Veracidad del cumplimiento NOM-035 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que nada de lo que Humetria muestra como cumplido en NOM-035 sea falso ante una inspección: cierre con participación a la vista, 7.8 solo cuando las personas pueden ver los resultados, y un expediente que sí trae los seis renglones.

**Architecture:** La fuente única de estado sigue siendo `Tenancy\Support\Nom035Route` → `Nom035Facts` → `Nom035Stages` / `Nom035Rows`; se le agrega la participación. La regla de «entregado» de Disclosure (`CenterFacts::delivered()`) se aplica también a D7 y a `DisclosureStanding::sharedEvaluations()`, que hoy la saltan. El expediente nuevo es una página de Tenancy que imprime los renglones de `Nom035Rows` y enlaza la evidencia imprimible de cada módulo; no lee tablas ajenas.

**Tech Stack:** Laravel 13, Pest, Inertia + React, Postgres. Sin dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (H-01..H-07, H-09, H-10, H-12, H-13, H-15, E-13, E-20, C-20; decisiones 1, 2, 32, 33) · **Manda:** `docs/normas/nom-035/criterios.md` (C-03, C-08, C-18, C-25, C-28, C-52, C-54) · Specs: `2026-10-29-nom-035-campaigns-design.md`, `2026-11-03-nom-035-policy-portal-design.md`, `2026-11-03-nom-035-intervention-program-design.md`.

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario, sin atribución a IA. PHPStan `max` y TS strictest sin supresiones ni `as`.
- **Privacidad:** ninguna prop nueva lleva respuestas individuales, horas exactas de respuesta ni nada que ligue persona y respuesta de Guía II/III. Los conteos de participación son por evaluación, nunca por persona. El expediente no incluye nombres de la Guía I: enlaza a Canalización, que ya exige `view-trauma-screenings`.
- Otro módulo se lee por `Contracts`; Tenancy no importa modelos de Campaigns, Disclosure, Results ni Interventions.
- El panel tutea. Textos en es-MX. Identificadores en inglés.
- Logs `modulo.accion` en pasado; ver el expediente va a la bitácora con `RecordAudit`.
- Migración con prefijo `2027_01_10_*`; columnas de fecha y hora `timestampTz`.
- Página nueva: browser test con `visitPage()`, entrada en `tests/Unit/PaginatedListsTest.php` si lleva arreglos (con su razón), revisión en claro, oscuro y 375 px contra `docs/rediseno/pantallas/png/V3-Inspeccion.png` y `V2-Ruta-N035.png`.
- Rama `fix/launch-compliance-nom035`. No se tocan `components/humetria/inspection-*.tsx` (son de F2).
- La lógica nueva declara `mutates(Clase::class)` en su test. La mutación no se corre.

## Review Focus

1. **Evaluación «a todo el centro» cerrada con 0 respuestas.** El diálogo lo dice, exige confirmar, y la ruta muestra «contestaron 0 de 20», no un «Hecho» limpio. → Task 1, Task 2.
2. **Evaluación de Guía III con muestra cumplida pero con personas sin Guía I.** El aviso distingue «faltan N por la Guía I» de la muestra; no se mezclan. → Task 1.
3. **Resultados publicados, página de las personas activa y luego revocada.** 7.8 vuelve a «Falta» en el tablero, la ruta y la inspección el mismo día. → Task 4.
4. **Centro de hasta 15 personas (solo Guía I) en el expediente.** Los renglones que no aplican dicen «No aplica» con su numeral; no aparecen como faltantes ni como cumplidos. → Task 5.
5. **Dos recordatorios desde dos pestañas en el mismo segundo.** Solo uno envía; el otro recibe el aviso de espera. → Task 7.

---

### Task 1: Cerrar con faltantes exige confirmar (H-01)

**Files:**
- Modify: `docs/normas/nom-035/criterios.md` (criterio nuevo **C-57 · Participación y vigencia**)
- Modify: `app/Modules/Campaigns/Support/CampaignPages.php:~205-218` (`progress()`), `app/Modules/Campaigns/Actions/CloseCampaign.php`, el FormRequest de cerrar (o crear `app/Modules/Campaigns/Http/Requests/CloseCampaignRequest.php` si `CampaignActionController::close` no tiene uno)
- Modify: `resources/js/lib/evaluation.ts`, `resources/js/components/campaigns/active-campaign-actions.tsx:46-49`, `resources/js/components/campaigns/close-dialog.tsx`, tipo de `progress` en `resources/js/types`
- Test: `tests/Feature/Campaigns/CampaignPagesTest.php`, `tests/Feature/Campaigns/CloseCampaignTest.php`, `tests/js/lib/evaluation.test.ts`, `tests/Browser/CampaignsTest.php`

**Interfaces:**
- Produces (PHP): `progress` gana `screeningPending: int` (personas con la Guía I pendiente) junto a `completed`, `target`, `againstSample`.
- Produces (TS): `closeShortfall(progress): { main: number; screening: number; againstSample: boolean }` en `lib/evaluation.ts`; `main = max(target - completed, 0)` **siempre**, no solo con muestra.
- Produces (HTTP): cerrar acepta `acknowledged_shortfall` (boolean); es `accepted` cuando `main > 0 || screening > 0`.

- [ ] **Step 1: Escribir C-57 en `criterios.md`.** Texto: una evaluación cerrada cuenta para la periodicidad del 7.9 (vigente dos años) con la participación que tuvo; la norma pide las guías a todas las personas del centro (C-04, C-08) o a la muestra (C-25) pero no fija un mínimo de respuestas. Por eso Humetria no bloquea el cierre: lo advierte, exige confirmarlo, lo deja en la bitácora y muestra «contestaron X de Y» en todo lugar donde diga que la evaluación está vigente. Fuente: num. 7.9, II.2 c), III.2 c).
- [ ] **Step 2: Tests que fallan.**
  - `CampaignPagesTest`: evaluación de 20 con 10 Guías II completas y 11 Guías I → `progress.screeningPending === 9`, `target === 20`, `completed === 10`.
  - `CloseCampaignTest`: `it('refuses to close with people left unless it was acknowledged')` → 422 con error en `acknowledged_shortfall`; con `acknowledged_shortfall=1` cierra y la entrada de bitácora `campaign.closed` lleva `acknowledged_shortfall => true`, `completed`, `target`. Caso borde: con todas contestadas cierra sin el campo.
  - `evaluation.test.ts`: `closeShortfall` con y sin muestra, con `target < completed` (da 0), con `progress === null`.
- [ ] **Step 3:** Correr los tres archivos → fallan por prop y regla inexistentes.
- [ ] **Step 4:** Implementar. El diálogo, cuando hay faltantes, muestra un `Alert` «Faltan N personas por contestar la Guía II/III» y/o «M personas no han contestado la Guía I», la frase «La norma pide las guías a todas las personas del centro. Si cierras ahora, quedará registrado que contestaron X de Y.» y una casilla «Entiendo y quiero cerrar así»; «Cerrar evaluación» queda deshabilitado hasta marcarla. Con muestra, el texto actual de `shortBy` se conserva.
- [ ] **Step 5:** Verde en los tres. Browser: en `CampaignsTest`, caso que abre el diálogo con faltantes, afirma el texto y que el botón está deshabilitado hasta marcar la casilla.
- [ ] **Step 6: Commit** `Cerrar una evaluación con personas sin contestar exige confirmarlo y queda en la bitácora (C-57)`.

### Task 2: «Vigente» siempre dice con cuántas respuestas (H-01)

**Files:**
- Modify: `app/Modules/Tenancy/Support/Nom035Rows.php:89-95` (`evaluation()`), `app/Modules/Tenancy/Support/Nom035Route.php:113-119` (`answered()`), `app/Modules/Campaigns/Support/CompanyEvaluationSchedule.php:37-49` y su DTO en `Campaigns/Data`
- Modify: `resources/js/lib/nom035/route.ts:16`, `resources/js/lib/work-centers.ts:70`
- Test: `tests/Feature/Tenancy/Nom035RoutePageTest.php`, `tests/Unit/Tenancy/Nom035StagesTest.php`, `tests/js/lib/work-centers.test.ts`, `tests/js/lib/nom035/route.test.ts`

**Interfaces:**
- Produces: la fila de etapa 2 en estado `done` lleva `detail = "Cerrada el {fecha}: contestaron {answered} de {target}"`. El DTO de `EvaluationSchedule` gana `answered: ?int` y `target: ?int` (null si nunca se evaluó).
- El estado de la fila **no** cambia (decisión 1): sigue `done`.

- [ ] **Step 1: Tests que fallan.** `Nom035RoutePageTest`: centro con evaluación cerrada 10 de 20 → la fila `7.1 y 7.9` trae `detail` con «contestaron 10 de 20» tanto en la ruta como en `inspection`. Cerrada 20 de 20 → «contestaron 20 de 20». `work-centers.test.ts`: estado `due` con `answered < target` → etiqueta «Al día · contestaron 10 de 20»; con `answered === target` → «Al día».
- [ ] **Step 2:** Correr → fallan.
- [ ] **Step 3:** Implementar. `Nom035Rows::evaluation()` recibe los conteos de `Nom035Route::answered()`, que ya llegan a las props. `route.ts` cambia «Hecho: la evaluación está vigente» por «Hecho: la evaluación está vigente (contestaron X de Y)».
- [ ] **Step 4:** Verde. Afirmar en `Nom035RoutePageTest` que el número de consultas de la página no sube (`expectsDatabaseQueryCount` si el test ya lo fija).
- [ ] **Step 5: Commit** `La ruta, la inspección y la tarjeta del centro dicen con cuántas respuestas se cerró la evaluación`.

### Task 3: El método del informe 7.7 dice quién contestó (H-01)

**Files:**
- Modify: `app/Modules/Results/Support/ReportBuilder.php:128-140`
- Test: `tests/Feature/Results/ReportTest.php`

- [ ] **Step 1: Test que falla.** Evaluación de 20 con 10 respuestas → el texto del método contiene «Se invitó a las 20 personas del centro; contestaron 10.» y ya no «aplicado a todos los trabajadores». Con muestra: «Se invitó a una muestra de 80 de 100 personas; contestaron 76.»
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** `Informe 7.7: el método dice a cuántas personas se invitó y cuántas contestaron`.

### Task 4: 7.8 solo cumple si las personas pueden ver los resultados (H-02)

**Files:**
- Modify: `app/Modules/Disclosure/Support/Requirements.php:127-153` (`results()`), `app/Modules/Disclosure/Support/CompanyDisclosureStanding.php:21-30` (`sharedEvaluations()`)
- Modify: `resources/js/components/disclosure/publish-results-button.tsx`
- Test: `tests/Feature/Disclosure/BoardResultsTest.php`, `tests/Feature/Disclosure/StandingContractTest.php`, `tests/Unit/Tenancy/Nom035StagesTest.php`, `tests/Browser/DisclosureTest.php`

- [ ] **Step 1: Tests que fallan.**
  - `BoardResultsTest`: publicada, sin página activa ni medio físico que cubra `e` → D7 `Missing` con detalle «Están publicados, pero no están a la vista: activa la página de las personas o registra un medio físico.» Con página activa → `Met`. Con `DisseminationRecord` que cubre `e` en ese centro → `Met`. Página activa y luego revocada → `Missing`.
  - `StandingContractTest`: `sharedEvaluations()` no incluye una evaluación publicada que no está entregada.
- [ ] **Step 2:** Correr → fallan.
- [ ] **Step 3:** Implementar con `$facts->delivered('e')`, la misma regla que `block()` usa para 5.7 c (criterio C-52). En `sharedEvaluations()` la condición es la misma, por centro (`disclosure_result_publications.work_center_id`).
- [ ] **Step 4:** El diálogo de publicar, cuando la página de las personas no está activa, agrega «Aún no tienen dónde verlos» y el botón «Crear el enlace» hacia la página que la activa.
- [ ] **Step 5:** Verde, incluido `Nom035StagesTest` (etapa de difusión no se da por hecha sin entrega).
- [ ] **Step 6: Commit** `7.8 cumple solo cuando los resultados publicados están a la vista de las personas (C-52)`.

### Task 5: Expediente NOM-035 por centro (H-03)

**Files:**
- Create: `app/Modules/Tenancy/Support/Nom035Dossier.php` (arma las props a partir de `Nom035Route` y `Nom035Rows`), controlador invocable junto al que sirve `tenancy/nom035-inspection`, ruta `tenant.nom035.dossier` en el mismo archivo de rutas que la inspección
- Create: `resources/js/pages/tenancy/nom035-dossier.tsx`, `resources/js/components/tenancy/nom035-dossier-row.tsx`
- Modify: `app/Modules/Tenancy/Support/RoutePage.php:46,66` (`dossierHref` → `tenant.nom035.dossier`)
- Test: `tests/Feature/Tenancy/Nom035DossierTest.php` (nuevo), `tests/Browser/Nom035RouteTest.php`, `tests/Unit/PaginatedListsTest.php` (`rows`: «resumen corto, seis renglones fijos de la tabla de inspección»)

**Interfaces:**
- Produces: página `tenancy/nom035-dossier` con props `center: {name, address}`, `issuedOn: string`, `readyOnly: bool`, `rows: list<{numeral, title, state, detail, evidence: {label, href} | null}>`. `?ready=1` deja solo `state === 'done'`, igual que los expedientes de Telework y Seating.
- Evidencia por renglón (enlaces a páginas imprimibles que ya existen): política y difusión → `tenant.disclosure.dossier`; evaluación → `tenant.campaigns.results`; informe 7.7 → `tenant.campaigns.report`; canalización 5.5 y 5.8 c → `tenant.campaigns.screenings` solo si el usuario puede `view-trauma-screenings`; Programa → su ruta de Interventions.

- [ ] **Step 1: Tests que fallan** en `Nom035DossierTest.php` (declara `mutates(Nom035Dossier::class)`):
  - los seis renglones, en el orden de la tabla, con su estado y detalle (incluye «contestaron X de Y» de la Task 2);
  - `?ready=1` deja solo los cumplidos;
  - centro de hasta 15 personas: los renglones de Guía II/III y 7.8 salen «No aplica»;
  - viewer: el renglón de canalización no trae `href` y dice «Solo lo ve quien puede ver la Guía I»;
  - centro de otra empresa → 404; sin sesión → redirige a login;
  - las props no contienen `answers`, nombres de personas ni marcas de tiempo (afirmación de privacidad);
  - escribe `nom035.dossier_viewed` en la bitácora.
- [ ] **Step 2:** Correr → 404 de ruta.
- [ ] **Step 3:** Implementar backend. `RoutePage::dossierHref` apunta a la ruta nueva.
- [ ] **Step 4:** Implementar la página partiendo de `pages/seating/dossier.tsx` (hoja imprimible, `print-button`, pestañas «Completo» / «Solo lo que tienes»). Referencia: `docs/rediseno/pantallas/png/V3-Inspeccion.png`.
- [ ] **Step 5:** Browser: `visitPage('tenancy/nom035-dossier', …, $dark, $mobile)` en `Nom035RouteTest.php`. Revisar claro, oscuro, 375 px y la vista de impresión.
- [ ] **Step 6: Commit** `Expediente NOM-035 por centro: los seis renglones de la inspección con su evidencia`.

### Task 6: Lanzar pide confirmación (H-04, criterio C-03)

**Files:**
- Modify: `resources/js/components/campaigns/launch-form.tsx:38-96`, `resources/js/lib/evaluation.ts`
- Test: `tests/js/lib/evaluation.test.ts`, `tests/Browser/CampaignsTest.php`

- [ ] **Step 1: Tests que fallan.** `launchSummary(people, closesOn)` → «Se enviarán 20 correos hoy. La evaluación cierra el 26 de noviembre.» (singular con 1). Browser: clic en «Lanzar e invitar» abre el diálogo y la evaluación sigue en borrador hasta confirmar.
- [ ] **Step 2–3:** Falla → implementar con `components/humetria/confirm-dialog.tsx`. El diálogo agrega: «Solo recibe la evaluación quien está en Empleados: revisa que tu plantilla esté completa.»
- [ ] **Step 4:** Verde. `LaunchCampaignTest` no cambia (el servidor es el mismo).
- [ ] **Step 5: Commit** `Lanzar una evaluación pide confirmación con el número de correos y el cierre`.

### Task 7: Recordatorios con espera (H-05)

**Files:**
- Create: `database/migrations/2027_01_10_100000_add_last_resent_at_to_campaigns_table.php` (`timestampTz('last_resent_at')->nullable()`)
- Modify: `config/campaigns.php` (`'resend_cooldown_hours' => 12`), `app/Modules/Campaigns/Actions/ResendInvitations.php:31-56`, `CampaignActionController::resend`, `CampaignPages` (prop `lastResentOn`: día local, sin hora), el diálogo de recordar
- Test: `tests/Feature/Campaigns/CampaignActionsTest.php`

- [ ] **Step 1: Tests que fallan** (con `Notification::fake()` y `travel`):
  - segundo recordatorio a las 11 h 59 min → no envía nada y vuelve con el error «Ya enviaste un recordatorio hoy. Podrás enviar otro en N horas.»;
  - a las 12 h exactas → envía;
  - dos peticiones seguidas en la misma transacción (`lockForUpdate` sobre la campaña) → una sola envía;
  - la prop `lastResentOn` es una fecha sin hora.
- [ ] **Step 2–4:** Falla → migración, acción y controlador → verde.
- [ ] **Step 5:** El diálogo muestra «Último recordatorio: {día}» y deshabilita el botón dentro de la espera.
- [ ] **Step 6: Commit** `Recordatorios: uno cada 12 horas por evaluación, con la fecha del último a la vista`.

### Task 8: Informe 7.7: cédula válida, responsable sugerido, fecha y firma (H-06, decisión 32)

**Files:**
- Modify: `app/Modules/Results/Http/Requests/ReportDetailsRequest.php:32`, `app/Modules/Results/Support/ReportBuilder.php` (`form()`, prop `issuedOn`), `resources/js/components/results/report-incises.tsx:81-94`, `report-header.tsx`
- Test: `tests/Feature/Results/ReportTest.php`

- [ ] **Step 1: Tests que fallan.** Dataset de cédula: `'abc'`, `'123456'`, `'123456789'` → 422 «La cédula profesional son 7 u 8 dígitos.»; `'1234567'`, `'12345678'`, `null` → pasa. El formulario parte del responsable del Programa cuando el del informe está vacío (`ProgramOutline::responsible`). La página trae `issuedOn` (día local de hoy).
- [ ] **Step 2–4:** Falla → `regex:/^\d{7,8}$/` con su mensaje → verde.
- [ ] **Step 5:** En la impresión (`print:block`), «Fecha de emisión: {issuedOn}» y una línea de firma bajo el responsable.
- [ ] **Step 6: Commit** `Informe 7.7: cédula de 7 u 8 dígitos, responsable del Programa por omisión, fecha de emisión y firma al imprimir`.

### Task 9: Programa: responsable heredado a la vista, etiqueta única y tabla en celular (H-07, H-12)

**Files:**
- Modify: `app/Modules/Interventions/Http/Requests/ActionRequest.php` (`attributes(): ['due_on' => 'fecha límite']`; **no** se cambia `lang/es/validation.php`: Engagement y Seating usan `due_on` como «fecha compromiso»), `resources/js/components/interventions/responsible-fields.tsx`, `program-rows.tsx:59`, `program-actions.tsx:52`
- Test: `tests/Feature/Interventions/ActionsHttpTest.php`, `tests/Browser/InterventionsTest.php`

- [ ] **Step 1: Tests que fallan.** Acción sin fecha → el mensaje de error contiene «fecha límite». Browser a 375 px: el menú «…» de una acción está dentro del viewport sin desplazar la tabla.
- [ ] **Step 2–3:** Falla → implementar. `responsible-fields.tsx` muestra como `placeholder` «Si lo dejas vacío: {responsable del Programa}». Las filas del Programa y de acciones usan tarjetas apiladas bajo `md`, con el patrón de `components/attendance/report-day-row.tsx`.
- [ ] **Step 4:** Verde. Revisar a 375 px en claro y oscuro contra `V2-N035-Plan.png`.
- [ ] **Step 5: Commit** `Programa: el responsable heredado se ve en el campo, «fecha límite» en el error, y tarjetas en el celular`.

### Task 10: Textos y pasos sueltos (H-09, H-10, H-13, E-13, H-15, E-20, C-20, política por centro)

Un commit por comportamiento; cada uno con su test de `lib` (Vitest) o de correo.

| Hallazgo | Archivo | Cambio | Test |
|---|---|---|---|
| H-09 | `components/results/confidential-banner.tsx:12` | «Solo la ven quienes administran la empresa y Recursos Humanos.» | `tests/Browser/CampaignsTest.php` (texto) |
| H-10 | `lib/report-findings.ts:82-89,114` | «la fortaleza» solo si el dominio no tiene respuestas en alto ni muy alto; si no, «el dominio con menor riesgo» | `tests/js/lib/report-findings.test.ts` |
| H-10 | `hooks/use-count-up.ts` | Al imprimir (`beforeprint` y `matchMedia('print')`) el valor es el final | `tests/Browser/CampaignsTest.php` (informe: sin cifras intermedias tras `emulateMedia print`) |
| H-13, E-13 | `app/Modules/Campaigns/Notifications/SurveyInvitation.php:58,84-86` | El correo dice «Tiene hasta el {lastDayLong}» en lugar de contar días | `tests/Feature/Campaigns/SurveyEmailsTest.php` |
| H-13 | `components/campaigns/invitation-preview.tsx:44-50` | Agrega la línea de que la Guía I lleva nombre | navegador |
| H-13 | `components/disclosure/share-preview.tsx` | Muestra los niveles, como la página real | navegador |
| H-13 | `resources/views/mail/partials/link.blade.php:2` | Espacio antes del `<br>` | `tests/Feature/MailInventoryTest.php` (texto plano con «navegador: http») |
| H-15 | `components/tenancy/work-center-card.tsx:62`, `lib/work-centers.ts:42-47` | Con 0 personas, botón «Cargar personas» a `employees.import` | `tests/js/lib/work-centers.test.ts`, `tests/Browser/WorkCentersTest.php` |
| E-20 | `pages/disclosure-public/center.tsx:26-27,74-78`, `lib/disclosure-public.ts` | `pendingNotice(policy, results)` nombra solo lo que falta | `tests/js/lib/disclosure-public.test.ts` |
| C-20 | `components/disclosure/public-complaint-card.tsx:24`, `share-preview.tsx:74-75`, `app/Modules/Disclosure/Support/Writing/ComplaintTemplate.php:60` | «Nadie recibirá represalias» → «Puede avisar sin dar su nombre.» (`ProcedureModel` no se toca: es compromiso de la empresa) | `tests/Feature/Disclosure/*` que afirme el texto de la plantilla |
| Política por centro | `components/disclosure/center-picker.tsx:28`, `publish-dialog.tsx:36` | Llegando con `?center=`, el selector preselecciona solo ese centro | `tests/Browser/DisclosureTest.php` |

- [ ] Por cada fila: test que falla → cambio → verde → commit.

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Revisión en navegador: ruta, inspección, expediente, cierre, informe y Programa en claro, oscuro y 375 px.
- [ ] Preguntar «¿Creo el PR?».
