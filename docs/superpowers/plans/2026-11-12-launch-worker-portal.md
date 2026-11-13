# F3 · Portal y encuesta del trabajador — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que una persona con un solo enlace llegue a todas sus herramientas, que RH pueda reenviárselo, y que cada encuesta hable de lo que es.

**Architecture:** El enlace personal sigue siendo el de Telework (`PortalLink`, un token por empleado) y sus secciones siguen en cada módulo. Lo que cambia: abrir el portal ya no exige NOM-037 (cada sección comprueba su propia herramienta), y la portada deja de ser «Su teletrabajo» para listar lo que cada módulo ofrece a esa persona mediante un contrato nuevo, `Telework\Contracts\PortalSection`, que cada módulo implementa y etiqueta en su provider. La encuesta recibe el tipo de campaña para elegir su portada.

**Tech Stack:** Laravel 13, Pest, Inertia + React. Sin migraciones ni dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (N-01, E-02, E-04, E-07, E-08, E-10, E-11, E-12, E-18, E-19, E-21, C-03, C-15, C-17, H-11, H-14; decisiones 3, 4, 23, 24) · Specs: `2026-11-03-nom-035-policy-portal-design.md` («Limitación conocida del enlace personal», «Página permanente»), `2026-10-29-nom-035-campaigns-design.md` («Respuestas»), `2026-11-03-nom-035-complaints-channel-design.md`, `2026-11-02-ley-silla-design.md` («Encuesta confidencial») · **Manda:** `docs/normas/nom-035/criterios.md` (C-07, C-18, C-27, C-28).

## Global Constraints

- TDD estricto; un commit = un comportamiento; commits a nombre del usuario. PHPStan `max` y TS strictest sin supresiones ni `as`.
- **Privacidad (invariante):** nada de este frente guarda ni envía respuestas parciales; el avance de la encuesta sigue solo en `localStorage` (E-05 y H-14 son intencionales). La pantalla final de la encuesta dice lo mismo a todas las personas: nunca revela si alguien requiere valoración. El token del portal nunca va a logs ni a la bitácora.
- Todo lo que ve la persona trabajadora habla de **usted**: pantallas, correos, toasts y páginas de error.
- Telework no importa internos de Leave, Seating, Disclosure, Engagement, Results ni Organization; ellos implementan `Telework\Contracts\PortalSection`. `tests/Unit/ArchitectureTest.php` debe seguir en verde sin tocar reglas.
- Las reglas de Ley Silla no entran a Campaigns ni a Responses (diseño de Ley Silla): la encuesta solo recibe el tipo de campaña.
- La clave de una queja no se guarda en el dispositivo ni se recupera (spec de quejas).
- Rutas nuevas con nombre; ninguna ruta pública nueva (decisión 4).
- Referencias de diseño: `docs/rediseno/pantallas/png/N037-Portal.png`, `LS-Portal.png`, `V4-Pagina-Personas.png`, `V4-Quejas-Form.png`, `V4-Quejas-Seguimiento.png`. Todo se revisa primero a 375 px, luego claro y oscuro.
- Rama `fix/launch-worker-portal`. Empieza con F0 y F2 ya en `main`.

## Review Focus

1. **Empresa con Vacaciones y sin NOM-037.** `/portal/{token}/vacations` abre; `/portal/{token}/checklists/...` da 404; la portada no menciona teletrabajo. → Task 1, Task 2.
2. **Empresa a la que le quitaron una herramienta ayer.** Su sección desaparece de la portada y su URL da 404, sin romper el resto del portal. → Task 1, Task 2.
3. **Persona dada de baja o enlace de una versión rotada.** Sigue viendo «Este enlace ya no está disponible» en todas las secciones. → Task 1.
4. **Enviar la encuesta sin señal, recuperar la señal a los dos minutos.** Se envía sola, una sola vez, y el enlace queda usado. → Task 5.
5. **Persona sin correo (solo celular) a la que RH quiere reenviar el enlace.** RH ve por qué no se puede y qué hacer; no hay envío silencioso a ninguna parte. → Task 3.

---

### Task 1: El portal abre sin NOM-037 (N-01)

**Files:**
- Modify: `app/Modules/Telework/Actions/OpenPortal.php:40`, `app/Modules/Telework/Data/OpenedPortal.php` (gana `bool $telework`), los controladores `Portal*` de Telework en `app/Modules/Telework/Http/Controllers` que sirven `routes/portal.php` (listas, evidencia, visitas, documentos, equipo, avisos)
- Test: `tests/Feature/Telework/PortalAccessContractTest.php`, `tests/Feature/Telework/PortalTest.php`, `tests/Feature/Leave/PortalVacationsTest.php`

**Interfaces:**
- Produces: `OpenedPortal::$telework` = la empresa tiene NOM-037 activo. `OpenPortal` ya no lanza `PortalUnavailable` por `stopped('nom037')`. Las páginas propias de Telework responden 404 cuando `! $portal->telework`.

- [ ] **Step 1: Test que demuestra el problema.** En `PortalVacationsTest.php`: `it('opens vacations for a company without NOM-037')` → empresa con el módulo de Vacaciones y sin `nom037`, `GET /portal/{token}/vacations` → 200. En `PortalAccessContractTest.php`: `PortalAccess` abre para esa empresa con `telework === false`.
- [ ] **Step 2:** Correr → 404. **Si pasa, N-01 no existe: se borra esta tarea y se anota en el índice.**
- [ ] **Step 3: Tests de frontera.** Empresa sin `nom037`: cada ruta de `routes/portal.php` distinta de la portada → 404 (dataset con los nombres de ruta). Persona inactiva, token inventado y token de versión anterior → siguen en «no disponible». Cada sección ajena con su herramienta apagada → 404 (un caso por módulo: Leave, Seating, Disclosure, Engagement, Organization, Results); si alguna hoy no lo comprueba, se corrige en su controlador.
- [ ] **Step 4:** Implementar. La comprobación de módulo vive en cada sección, no en `OpenPortal`.
- [ ] **Step 5:** Verde en los tres archivos y en `tests/Feature/Seating` y `tests/Feature/Disclosure` filtrados por `Portal`.
- [ ] **Step 6: Commit** `El portal de la persona abre aunque la empresa no tenga NOM-037; cada sección exige su propia herramienta`.

### Task 2: La portada lista las herramientas de la persona (E-02, E-21)

**Files:**
- Create: `app/Modules/Telework/Contracts/PortalSection.php`, `app/Modules/Telework/Data/PortalSectionLink.php`
- Create (una clase por módulo, en su `Support/`, etiquetada `portal.sections` en su provider): Leave (Mis vacaciones; Mi equipo si es jefe), Seating (Ley Silla, si tiene puesto de pie), Disclosure (Política por confirmar; «Avisos y resultados de su centro» hacia `/workers/{token}` si la página está activa), Engagement (Clima o Retroalimentación con invitación abierta), Results (Apoyo, si tiene uno en espera), Organization (Mi equipo), Telework (sus pendientes, si es teletrabajador)
- Modify: `PortalController` de Telework (prop `sections`), `resources/js/pages/telework/portal.tsx:24`, `resources/js/components/telework/portal/portal-home.tsx:34`, `resources/js/components/disclosure/workers-shell.tsx`
- Test: `tests/Feature/Telework/PortalTest.php`, un caso en el test de portal de cada módulo, `tests/Unit/PaginatedListsTest.php` (`sections`: «resumen corto: las herramientas de una persona»), `tests/Browser/LeavePortalTest.php`

**Interfaces:**
- `interface PortalSection { public function for(OpenedPortal $portal, string $token): ?PortalSectionLink; }` — devuelve `null` si la herramienta está apagada o no hay nada para esa persona.
- `final readonly class PortalSectionLink { public function __construct(public string $key, public string $label, public string $description, public string $href, public ?int $pending = null) {} }`
- La portada recibe `sections: list<{key, label, description, href, pending}>` en orden fijo: telework, vacations, team, seating, policy, climate, feedback, support, center.

- [ ] **Step 1: Tests que fallan.**
  - `PortalTest`: persona no teletrabajadora en empresa con Vacaciones y Ley Silla → `sections` trae `vacations` y `seating`, no `telework`; el título de la página es «Su portal».
  - Teletrabajadora → `telework` va primero con su número de pendientes.
  - Herramienta apagada → su sección no aparece.
  - Página de las personas activa → sección `center` con el enlace a `/workers/{token}`.
  - Las props no traen datos de otra persona ni el token en claro fuera de los `href`.
- [ ] **Step 2:** Correr → fallan.
- [ ] **Step 3:** Implementar el contrato, las implementaciones y la prop. Cada implementación hace como mucho una consulta; la portada fija su total con `expectsDatabaseQueryCount`.
- [ ] **Step 4:** `portal-home.tsx`: tarjetas grandes (mínimo 56 px de alto), una por sección, con su pendiente; estado vacío «Por ahora no tiene nada pendiente» con el dato de contacto de RH. Texto neutro: se quita «para su teletrabajo» salvo en la tarjeta de Telework.
- [ ] **Step 5:** `workers-shell.tsx`: una línea fija «Sus trámites personales (vacaciones, confirmaciones) están en el enlace personal que llegó a su correo.»
- [ ] **Step 6:** Verde. Navegador a 375 px, claro y oscuro: portada → Vacaciones → «Volver a mi portal» → portada con enlaces (ya no es callejón).
- [ ] **Step 7: Commit** `Portal de la persona: una portada con todas sus herramientas y el puente a la página de su centro`.

### Task 3: RH reenvía el enlace del portal (E-08, decisión 4)

**Files:**
- Create: `app/Modules/Telework/Contracts/PortalLinks.php` (`sendTo(int $employeeId): bool`), su implementación en `Telework/Support`, `app/Modules/Telework/Notifications/PortalLinkEmail.php` (neutro, de usted, `'formal' => true`)
- Modify: `app/Modules/Organization/Http/Controllers/EmployeeController.php` (acción `portalLink`), `routes/organization.php` (`POST employees/{employee}/portal-link`, nombre `tenant.employees.portal-link`, `manage-organization`, `throttle`), menú de la fila en `resources/js/components/organization/`, `resources/js/components/leave/send-links-bar.tsx:41` (etiqueta), `resources/js/components/telework/portal/portal-unavailable.tsx`
- Test: `tests/Feature/Organization/EmployeePortalLinkTest.php` (nuevo), `tests/Feature/Leave/SendPortalLinksTest.php`, `tests/Feature/MailInventoryTest.php`, `tests/Browser/EmployeesTest.php`

- [ ] **Step 1: Tests que fallan.**
  - RH envía el enlace a una persona con correo → `PortalLinkEmail` a ese correo; log `portal.link_sent` con `employee_id` y sin token (afirmar que ninguna línea de log contiene el token).
  - Persona sin correo → vuelve con «No tiene correo registrado: agrégalo en su ficha para enviarle el enlace.» y no se envía nada.
  - Persona dada de baja → 404. Viewer → 403. Empleado de otra empresa → 404.
  - El enlace enviado es el mismo que ya tenía (reenviar no lo rota).
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5:** `send-links-bar.tsx`: «Enviar constancia para acuse» → «Enviar enlace a su portal» (el envío es el mismo). `portal-unavailable.tsx`: «Pida a Recursos Humanos de su empresa que le reenvíe su enlace.»
- [ ] **Step 6: Commit** `RH reenvía a una persona el enlace de su portal desde Empleados`.

### Task 4: Cada encuesta con su portada (C-03, C-17)

**Files:**
- Modify: `app/Modules/Campaigns/Http/Controllers/SurveyController.php:59-66` (prop `kind` = `$survey->campaign->kind->value`)
- Modify: `resources/js/lib/survey.ts` (`introCopy(kind)` y `progressOf` en `:139-153`), `components/campaigns/survey/survey-intro.tsx:33-35,56-62`, `guide-intro.tsx:55`, `survey-flow.tsx:42`, `survey-step.tsx:51`, `pages/campaigns/survey.tsx:40,48`, tipo de la página
- Test: `tests/Feature/Campaigns/OpenSurveyTest.php`, `tests/Feature/Campaigns/SeatingSurveyTest.php`, `tests/js/lib/survey.test.ts`, `tests/Browser/CampaignsTest.php` o `SurveyTest.php`

**Interfaces:**
- `introCopy(kind: CampaignKind): { headTitle: string; title: string; purpose: string; period: string | null; noun: 'guía' | 'encuesta' }`. Para `seating_survey`: título «Encuesta sobre su trabajo de pie», propósito sin mención de NOM-035, `period: null`, `noun: 'encuesta'`. Para NOM-035, los textos actuales.

- [ ] **Step 1: Tests que fallan.** `SeatingSurveyTest`: la página trae `kind === 'seating_survey'`. `survey.test.ts`: `introCopy('seating_survey')` no contiene «NOM-035» ni «dos últimos meses»; `progressOf` con una pregunta-compuerta devuelve el mismo total que el pie de la pregunta («1 de 8» en los dos). Browser: la portada de la encuesta de Ley Silla no muestra «NOM-035».
- [ ] **Step 2–4:** Falla → implementar → verde. `OpenSurveyTest` confirma que NOM-035 no cambió.
- [ ] **Step 5: Commit** `La encuesta de Ley Silla tiene su propia portada y el contador cuenta lo mismo arriba y abajo`.

### Task 5: Enviar sin señal y qué pasa después de la Guía I (E-04, E-07)

**Files:**
- Modify: `resources/js/components/campaigns/survey/survey-runner.tsx:46-54`, `resources/js/components/engagement/survey/survey-runner.tsx:27-29` (con `hooks/use-resend-when-online.ts`, como `components/seating/portal-questions.tsx:43`), `resources/js/components/campaigns/survey/survey-done.tsx`
- Test: `tests/Browser/SurveyTest.php`, `tests/Browser/ClimateSurveyTest.php`

- [ ] **Step 1: Tests que fallan.** Browser con la red cortada (`setOffline(true)`): al enviar aparece «Sin conexión: se enviará sola cuando vuelva la señal. Sus respuestas siguen en este teléfono.»; al volver la red se envía una vez y llega a la pantalla final. Tras una encuesta NOM-035, la pantalla final muestra, igual para todas las personas: «Si algo de lo que contestó le sigue afectando, puede pedir atención a Recursos Humanos o acudir a su clínica del IMSS. Si sus respuestas indican que la necesita, Recursos Humanos le buscará.» En la de Ley Silla y en clima no aparece.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** (dos): `La encuesta avisa cuando no hay señal y se envía sola al volver` · `Al terminar la Guía I, toda persona ve a dónde acudir`.

### Task 6: De usted en correos, toasts y errores (E-12, HR-14)

**Files:**
- Modify: el `->markdown('notifications::email', [...])` de las 14 notificaciones al trabajador para pasar `'formal' => true`: Leave (`VacationsPortalLink`, `RequestDecided`, `RequestToDecide`), Attendance (`EnrollmentInvitation`, `DeviceLinked`), Disclosure (`PolicyToConfirm`), Results (`SupportWaiting`), Seating (`RisksToConfirm`, `StandingQuestions`), Telework (`PortalInvitation`, `PortalReminder`, `DocumentsToConfirm`, `NoticeAnswered`), Engagement (`FeedbackPublished`)
- Modify: `bootstrap/app.php:98-102` y `app/Http/ErrorPage.php:46` (`routeIs` también `portal.*`, `attendance.device.*`, `workers.*` y los nombres públicos de quejas)
- Test: `tests/Feature/MailInventoryTest.php`, `tests/Feature/ErrorPagesTest.php` (o el test que cubre `ErrorPage`)

- [ ] **Step 1: Tests que fallan.** En `MailInventoryTest`, dataset con esas 14 notificaciones: el HTML contiene «copie este enlace en su navegador» y no «en tu navegador». Un 429 en una ruta `portal.*` devuelve el toast de usted («Espere un minuto…»); un 404 en `workers.*` usa la página de error de usted.
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** `Todo lo que recibe la persona trabajadora la trata de usted: correos, avisos de espera y páginas de error`.

### Task 7: Encuesta legible al 200 % y aviso de equipo compartido (H-11, H-14)

**Files:**
- Modify: `components/campaigns/survey/survey-question.tsx:32,42,52`, `survey-step.tsx:40`, `guide-intro.tsx:30`, `survey-intro.tsx:70`, `survey-shell.tsx:19`
- Test: `tests/Browser/SurveyTest.php`

- [ ] **Step 1: Tests que fallan.** Browser a 375 px con `html { font-size: 200% }`: `document.documentElement.scrollWidth <= clientWidth` y el encabezado no está truncado; la pantalla de pregunta tiene un `h1`. La portada de la guía muestra «Sus respuestas se guardan en este equipo hasta que las envíe. Si el equipo es compartido, termine en una sola sesión.»
- [ ] **Step 2–3:** Falla → alturas de línea relativas (`leading-snug`, `leading-7`), el encabezado envuelve en lugar de truncar, `h1` con `sr-only` y la línea de aviso.
- [ ] **Step 4:** Verde, axe incluido. Revisar a 375 px, claro y oscuro.
- [ ] **Step 5: Commit** `Encuesta: legible con el texto al doble, con encabezado para lectores de pantalla y aviso de equipo compartido`.

### Task 8: Canal de quejas: promesa exacta, clave y fecha (C-15, E-18, E-19)

**Files:**
- Modify: `resources/js/components/complaints-public/form-intro.tsx:63-64`, `receipt-card.tsx:39-48`, `code-row.tsx`, `step-story.tsx:56-62`, `resources/js/lib/complaints-receipt.ts`
- Test: `tests/js/lib/complaints-form.test.ts`, `tests/js/lib/complaints-receipt.test.ts`, `tests/Browser/ComplaintsTest.php`

- [ ] **Step 1: Tests que fallan.**
  - El texto de entrada es «La lee solo quien la empresa designó para atender quejas. Si su queja es contra Recursos Humanos o contra la dirección, no llega a ellos.» (el ruteo excluye por rol: `RespondentKind`).
  - El acuse incluye «Guárdela ahora: no se vuelve a mostrar. Si el dispositivo no es suyo, cierre esta pantalla al terminar.» (la frase que el spec pide y falta).
  - `shareText(reference, key)` arma el texto a compartir; el botón «Compartir» solo sale si `navigator.share` existe. Nada se escribe en `localStorage` (afirmarlo en el browser test).
  - El selector de fecha dice «Elija una fecha».
- [ ] **Step 2–4:** Falla → implementar → verde.
- [ ] **Step 5: Commit** (tres, uno por comportamiento).

### Task 9: Vacaciones en el portal: saldo en espera y doble toque (E-10, E-11)

**Files:**
- Modify: `app/Modules/Leave/Support/PortalVacationsPages.php:66` (prop `waiting`: suma de `working_days` de solicitudes de vacaciones en espera), `resources/js/pages/leave/portal.tsx` o su componente de saldo, `resources/js/components/leave/request-form.tsx:51,90`
- Test: `tests/Feature/Leave/PortalVacationsTest.php`, `tests/Browser/LeavePortalTest.php`

- [ ] **Step 1: Tests que fallan.** Persona con 12 días y una solicitud de 3 en espera → `available === 12`, `waiting === 3`, y la pantalla dice «3 días en espera de respuesta». Sin solicitudes → no aparece la línea. Browser: tras enviar, un segundo toque inmediato no pinta errores (el botón está deshabilitado mientras falten fechas o tipo).
- [ ] **Step 2–4:** Falla → implementar (el libro de movimientos no se toca) → verde.
- [ ] **Step 5: Commit** (dos).

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Recorrido a 375 px como José: correo → portal → vacaciones → portada → página del centro → queja.
- [ ] Preguntar «¿Creo el PR?».
