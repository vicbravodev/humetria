# Humetría como plataforma de herramientas de RH: plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Presentar a Humetría como plataforma de RH con tres herramientas (NOM-035, NOM-037 y Ley Silla) en la landing,
el formulario y la consola de demos, el menú de la app y los correos.

**Architecture:** Un enum `Tool` en el módulo Demos, persistido como `jsonb` con un `check` en Postgres, captura qué
herramientas le interesan al prospecto. La landing se reescribe por secciones, con una sección nueva `#herramientas`
alimentada por un arreglo de datos. El menú separa "Panel" de un grupo "Cumplimiento" que se oculta mientras esté
vacío.

**Tech Stack:** Laravel 13 + Pest 4 (Browser), Inertia v3 + React + TS strictest, Tailwind v4, Postgres, lucide-react.

**Spec:** `docs/superpowers/specs/2026-10-28-hr-tools-positioning-design.md`. Ahí están los hechos legales exactos y
las viñetas de cada herramienta: cópialos de ahí.

## Global Constraints

- TDD estricto: cada commit = test + código, todo en verde. Los tests viejos solo cambian donde el spec cambia el
  comportamiento (textos de NOM-035, etiquetas del formulario, pie de correo).
- Larastan max sin supresiones; TS sin `as`, `!`, `any`, `@ts-*`.
- Frontend: ≤ 300 líneas por archivo, ≤ 100 por componente, complejidad ≤ 10, ≤ 3 parámetros, un componente por
  archivo, kebab-case, imports con `@/`.
- Color solo con tokens; landing en es-MX tuteando; ningún texto visible menciona herramientas fuera de las tres.
- Privacidad en los textos: solo Guías II/III de NOM-035 y la encuesta de Ley Silla son anónimas; Guía I y las listas
  de NOM-037 son identificadas.
- Nada de montos de multas.
- No se abre PR; commits en `feature/hr-tools-positioning`.
- Por iteración: `php artisan test --compact --filter=…`. Al final: `composer test`, `composer test:mutate`,
  `npm run types:check`, `npm run test:js` y `composer test:browser` (requiere `npm run build`).

## Review Focus

1. `tools` enviado como string (`"nom_035"`) en lugar de arreglo → error de validación en español, no un 500 (Task 2).
2. `tools` con el mismo valor repetido → se rechaza (`distinct`), no se guarda duplicado (Task 2).
3. Formulario enviado vacío: el grupo de casillas recibe `aria-invalid` y su error, y el foco va al primer campo
   inválido (Task 3).
4. Tras enviar con éxito, las casillas quedan sin marcar (`resetOnSuccess`) (Task 3).
5. Fila antigua o insertada directo con `tools = '[]'` o con un valor desconocido → la base la rechaza (Task 1).

---

### Task 1: Enum `Tool` y columna `tools` en `demo_requests`

**Files:**
- Create: `app/Modules/Demos/Enums/Tool.php`
- Create: `database/migrations/2026_10_28_200000_add_tools_to_demo_requests_table.php`
- Modify: `app/Modules/Demos/Models/DemoRequest.php` (`@property`, `Fillable`, cast)
- Modify: `database/factories/Demos/DemoRequestFactory.php`
- Test: `tests/Feature/Demos/DemoRequestStorageTest.php`, `tests/Feature/Demos/DemoRequestConsoleTest.php`
  (dataset de etiquetas)

**Interfaces:**
- Produces: `enum Tool: string { Nom035 = 'nom_035'; Nom037 = 'nom_037'; LeySilla = 'ley_silla'; public function
  label(): string }`, con las etiquetas `'NOM-035'`, `'NOM-037'` y `'Ley Silla'`.
- Produces: `DemoRequest::$tools` es `Collection<int, Tool>` (cast `AsEnumCollection::of(Tool::class)`). La factory
  genera un subconjunto aleatorio no vacío.

- [ ] **Step 1: Tests que fallan.**
  - En `DemoRequestStorageTest`, agrega al dataset de "rejects a row the form would reject":
    - `'no tools' => ['tools', '[]']`;
    - `'unknown tool' => ['tools', '["nom_036"]']`;
    - `'tools not a list' => ['tools', '{"a":"nom_035"}']`;
    - `'missing tools' => ['tools', null]`.
  - Test nuevo: `it('keeps the tools a request asked about, in order')`. La factory crea con
    `tools => [Tool::LeySilla, Tool::Nom035]`, se hace `fresh()` y se espera
    `$demoRequest->tools->all()` igual a `[Tool::LeySilla, Tool::Nom035]`.
  - En `DemoRequestConsoleTest`, el dataset de "labels every profile and headcount range" agrega los tres casos de
    `Tool` con sus etiquetas, y el tipo del parámetro pasa a `DemoProfile|Workforce|Tool`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Demos/DemoRequestStorageTest.php`. FALLA: no existe la
  columna ni la clase `Tool`.
- [ ] **Step 3: Implementa.**
  - Migración: `jsonb('tools')`, `nullable` al crearla. Rellena las filas existentes con `'["nom_035"]'` y luego
    `ALTER COLUMN tools SET NOT NULL`.
  - Agrega `CONSTRAINT demo_requests_tools_valid CHECK (jsonb_typeof(tools) = 'array' AND jsonb_array_length(tools) > 0
    AND tools <@ '["nom_035","nom_037","ley_silla"]'::jsonb)`. `down()` borra la columna.
  - El enum lleva un docblock de una línea (las herramientas que ofrece Humetría, para saber qué busca el prospecto)
    y `label()` con `match`.
  - Actualiza el docblock de `DemoProfile` ("una empresa o grupo que usa Humetría para sí, o un consultor que la usa
    con sus clientes").
- [ ] **Step 4:** el mismo comando, más `tests/Feature/Demos/DemoRequestConsoleTest.php`. PASA.
- [ ] **Step 5:** commit "Store which tools a demo request is about".

### Task 2: El formulario exige y guarda las herramientas

**Files:**
- Modify: `app/Modules/Demos/Http/Requests/StoreDemoRequestRequest.php`
- Modify: `app/Modules/Demos/Data/DemoRequestData.php`
- Modify: `app/Modules/Demos/Actions/SubmitDemoRequest.php`
- Modify: `lang/es/validation.php` (`attributes.tools => 'herramientas'`)
- Test: `tests/Feature/Demos/DemoRequestTest.php`

**Interfaces:**
- Consumes: `Tool` (Task 1).
- Produces: `DemoRequestData::$tools` es `list<Tool>`. La regla de validación es
  `'tools' => ['required', 'array', 'min:1', 'max:3']` y `'tools.*' => ['required', 'distinct', Rule::enum(Tool::class)]`.

- [ ] **Step 1: Tests que fallan.**
  - El helper `demoRequest()` agrega `'tools' => ['nom_035', 'ley_silla']`.
  - "stores a demo request…" afirma `->tools->all()` igual a `[Tool::Nom035, Tool::LeySilla]`.
  - Test nuevo `it('rejects tools that are missing, empty, unknown, repeated or not a list', function (mixed $tools,
    string $error))` con este dataset:

    | Caso | Valor | Error esperado |
    |------|-------|----------------|
    | ausente | `null` | `'El campo herramientas es obligatorio.'` |
    | vacío | `[]` | `'El campo herramientas es obligatorio.'` |
    | string | `'nom_035'` | el mensaje de `array` en es |
    | desconocida | `['nom_036']` | se espera error en `tools.0` |
    | repetida | `['nom_035','nom_035']` | se espera error en `tools.1` |

    Usa `assertSessionHasErrors` con la llave y confirma que no se guardó nada.
  - En "logs the submission without any personal data", la línea del log incluye
    `'tools' => ['nom_035', 'ley_silla']`. Es dato comercial, no personal.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/Demos/DemoRequestTest.php`. FALLA.
- [ ] **Step 3: Implementa** las reglas de arriba.
  - `toData()` arma `tools` con `array_values(array_map(Tool::from(...), $this->array('tools')))`, tipado sin casts.
    Si Larastan se queja, valida con `$this->validated('tools')` y una función que acepte `mixed` y filtre strings.
  - El action guarda `tools` y lo registra en el log como valores.
  - Agrega `'tools' => 'herramientas'` a los atributos de `lang/es/validation.php`.
- [ ] **Step 4:** el mismo comando. PASA. Corre también `composer test:mutate` acotado si es rápido; si no, al final.
- [ ] **Step 5:** commit "Ask which tools a prospect wants in the demo request".

### Task 3: Casillas de herramientas y nuevo perfil en el formulario de la landing

**Files:**
- Create: `resources/js/components/landing/demo-tools-field.tsx`
- Modify: `resources/js/components/landing/demo-request-form.tsx`
- Modify: `resources/js/lib/first-invalid-field.ts`, solo si no enfoca `tools[]` con la llave de error `tools`
  (verifícalo con el test)
- Test: `tests/Browser/LandingTest.php`

**Interfaces:**
- Produces: `DemoToolsField({ error }: { error?: string | undefined })`. Es un `fieldset` con `aria-labelledby`,
  `aria-invalid` y `aria-describedby` como en `DemoChoiceField`, con `data-test="tools-group"`. Lleva tres
  `<input type="checkbox" name="tools[]" value="nom_035|nom_037|ley_silla">` dentro de labels con el mismo estilo
  `has-checked:` que `DemoChoiceField`.
  - Leyenda: "¿Qué herramientas te interesan?".
  - Cada opción muestra el nombre corto en negritas y una línea en `text-muted-foreground`:
    - NOM-035 → "Riesgo psicosocial";
    - NOM-037 → "Teletrabajo";
    - Ley Silla → "Asientos y descansos".

- [ ] **Step 1: Tests que fallan** (`LandingTest`).
  - `fillDemoForm` marca `tools[]` = `nom_035` y `nom_037` (por ejemplo con `->check('[value="nom_035"]')`, según la
    API de Pest Browser).
  - "stores a demo request…" afirma que `$demoRequest->tools` se mapea a `['nom_035', 'nom_037']` y que las casillas
    quedan sin marcar: `assertScript` con `[...document.querySelectorAll('[name="tools[]"]')].every(c => !c.checked)`.
  - "form sent empty": agrega `'tools' => 'El campo herramientas es obligatorio.'` al foreach de grupos.
  - Test nuevo `it('asks who Humetría is for')`: `assertSee('¿Para quién es Humetría?')`,
    `assertSee('Para mi empresa o grupo de empresas')` y `assertSee('Soy consultor y atiendo a varias empresas')`.
- [ ] **Step 2:** `npm run build && ./vendor/bin/pest tests/Browser/LandingTest.php`. FALLA.
- [ ] **Step 3: Implementa** el componente.
  - En `demo-request-form.tsx`: los textos de `PROFILES` pasan a `'Para mi empresa o grupo de empresas'` y
    `'Soy consultor y atiendo a varias empresas'`; la leyenda pasa a `'¿Para quién es Humetría?'`.
  - `<DemoToolsField error={errors['tools']} />` va entre el perfil y el número de trabajadores.
  - Si el foco no llega al grupo, ajusta `firstInvalidField` para que `tools` coincida con `tools[]`, con su test en
    Vitest.
- [ ] **Step 4:** el mismo comando. PASA; `npm run types:check`.
- [ ] **Step 5:** commit "Let prospects pick NOM-035, NOM-037 or Ley Silla in the demo form".

### Task 4: La consola de demos muestra las herramientas

**Files:**
- Modify: `app/Modules/Demos/Http/Controllers/DemoRequestConsoleController.php` (prop `'tools'`)
- Modify: `resources/js/types/*` (el tipo `DemoRequestRow`: `tools: string[]`)
- Modify: `resources/js/components/demos/demo-request-row.tsx` (badges `<Badge variant="secondary">` bajo el perfil, o
  una columna "Herramientas"; ajusta el encabezado en `demo-requests-table.tsx` si agregas columna)
- Test: `tests/Feature/Demos/DemoRequestConsoleTest.php`, `tests/Browser/DemoConsoleTest.php`

**Interfaces:**
- Consumes: `DemoRequest::$tools` (Task 1).
- Produces: la prop `demoRequests.data[].tools` es `list<string>` con etiquetas, por ejemplo `['NOM-035', 'Ley Silla']`.

- [ ] **Step 1: Tests que fallan.**
  - "lists every field…": la factory crea con `tools => [Tool::Nom035, Tool::LeySilla]` y la prop esperada es
    `->where('demoRequests.data.0.tools', ['NOM-035', 'Ley Silla'])`.
  - El conteo de consultas se mantiene en 4.
  - En `DemoConsoleTest`, el creado lleva esas herramientas y se agrega `->assertSee('Ley Silla')`.
- [ ] **Step 2:** corre ambos archivos. FALLA.
- [ ] **Step 3: Implementa**
  `'tools' => $demoRequest->tools->map(fn (Tool $tool): string => $tool->label())->values()->all()`, más el tipo y los
  badges.
- [ ] **Step 4:** PASA, con `npm run build` antes del browser.
- [ ] **Step 5:** commit "Show the tools each prospect asked about in the demo console".

### Task 5: Hero, título, header y vista previa de la landing

**Files:**
- Modify: `resources/js/pages/landing.tsx` (`<Head>`)
- Modify: `resources/js/components/landing/landing-hero.tsx`
- Modify: `resources/js/components/landing/landing-header.tsx` (ancla "Herramientas" → `#herramientas`)
- Modify: `resources/js/components/landing/hero-preview.tsx`. Pártelo si pasa de 100 líneas: por ejemplo
  `hero-preview-tool.tsx` para cada fila.
- Test: `tests/Browser/LandingTest.php`

**Interfaces:**
- Produces: el ancla `#herramientas` que Task 6 crea.

- [ ] **Step 1: Tests que fallan.**
  - `it('presents Humetría as an HR platform with its three tools')`:
    - `assertTitleContains('Herramientas de RH')`;
    - la meta description contiene "NOM-035", "NOM-037" y "Ley Silla" (`assertScript` sobre
      `document.querySelector('meta[name=description]').content`);
    - el eyebrow del hero es exactamente "NOM-035 · NOM-037 · Ley Silla";
    - el H1 `#hero-title` no contiene "NOM-035".
  - El test "marks the preview campaign with the design system badge" se reemplaza por `it('previews a work center
    with the state of the three tools')`. Afirma que `figure` contiene "NOM-035", "NOM-037" y "Ley Silla", y que la
    fila de NOM-035 conserva un `[data-slot="badge"]` con "En curso".
  - El test de CTAs agrega el caso `'header tools' => '@header-tools'`, cuyo hash esperado es `#herramientas`. Esto
    queda en rojo hasta Task 6: márcalo en el mismo commit de Task 6, o haz Task 5 y Task 6 como un solo commit si lo
    prefieres.
- [ ] **Step 2:** `npm run build && ./vendor/bin/pest tests/Browser/LandingTest.php`. FALLA.
- [ ] **Step 3: Implementa.**
  - Head: title "Herramientas de RH para cumplir con la STPS"; description, por ejemplo: "Humetría reúne NOM-035,
    NOM-037 y Ley Silla: invita a tu gente, recolecta de forma confidencial y te entrega el diagnóstico, el plan y la
    evidencia para la STPS."
  - H1: "Cumple con la STPS y cuida a tu gente, sin complicarte". El párrafo sigue el spec.
  - HeroPreview: el encabezado "Planta Querétaro · 120 trabajadores" y tres filas, cada una con ícono, nombre,
    estado y un dato:
    - NOM-035: badge "En curso", "Guía III · 68 de 92" y la barra 74 %;
    - NOM-037: "2 verificaciones por validar";
    - Ley Silla: "Plan de acción · 3 de 5".
  - Conserva la idea del mínimo por grupo solo si cabe sin pasar los límites. No es obligatorio.
- [ ] **Step 4:** PASA, salvo el caso `#herramientas` si lo dejas para Task 6. Compara con
  `docs/design-system/capturas/` en claro y oscuro.
- [ ] **Step 5:** commit "Lead the landing with Humetría as an HR platform for NOM-035, NOM-037 and Ley Silla".

### Task 6: Sección "Herramientas"

**Files:**
- Create: `resources/js/components/landing/tools-section.tsx` (`id="herramientas"`, `SectionHeading` existente)
- Create: `resources/js/components/landing/tool-card.tsx`
- Create: `resources/js/components/landing/landing-tools.ts`, solo datos (o en `resources/js/lib/` si se reutiliza;
  YAGNI: se queda en landing)
- Modify: `resources/js/pages/landing.tsx` (va después del hero)
- Test: `tests/Browser/LandingTest.php`

**Interfaces:**
- Produces: `type LandingTool = { key: 'nom_035' | 'nom_037' | 'ley_silla'; name: string; official: string;
  appliesTo: string; points: readonly string[]; icon: LucideIcon }` y `TOOLS: readonly LandingTool[]`.
  `ToolCard({ tool }: { tool: LandingTool })`.
- Los textos, copiados del spec:
  - `official`:
    - "NOM-035-STPS-2018 · Factores de riesgo psicosocial";
    - "NOM-037-STPS-2023 · Teletrabajo";
    - "Ley Federal del Trabajo, arts. 132 y 133 · Asientos y descansos".
  - `appliesTo`:
    - "Todo centro de trabajo";
    - "Quien tenga personas teletrabajando más del 40 % de su jornada";
    - "Todo patrón, en todo centro de trabajo".
  - `points`: las viñetas de "Qué hace Humetría con cada herramienta".

- [ ] **Step 1: Test que falla.** `it('details each of the three tools')`:
  - `#herramientas` existe;
  - contiene tres `[data-test="tool-card"]`;
  - cada tarjeta muestra su `official` exacto;
  - se ve "Lista de verificación" en la de NOM-037 y "reglamento interior" en la de Ley Silla;
  - la sección no contiene "próximamente" (sin distinguir mayúsculas).
  - Más el caso de CTA `@header-tools` de Task 5.
- [ ] **Step 2:** FALLA.
- [ ] **Step 3: Implementa.** Cuadrícula de 1 columna en móvil y 3 en `lg`. Cada tarjeta lleva:
  - el ícono de lucide (`BrainCircuit` o `HeartPulse` para NOM-035, `House` o `Laptop` para NOM-037, `Armchair` para
    Ley Silla);
  - el nombre como `h3`, el oficial en texto pequeño y "Aplica a: …";
  - la lista con `Check`.

  Al pie: "Humetría reúne en un solo lugar las obligaciones de RH ante la STPS."
- [ ] **Step 4:** PASA; corre `PagesTest` (axe, CSP, sin errores de JS) para la landing en claro y oscuro.
- [ ] **Step 5:** commit "Detail NOM-035, NOM-037 and Ley Silla in a tools section".

### Task 7: Audiencia, Cómo funciona, Privacidad y Demo

**Files:**
- Modify: `resources/js/components/landing/audience-section.tsx` (tres perfiles: consultor con varias empresas,
  empresa con varios centros de trabajo, grupo con varias razones sociales)
- Modify: `resources/js/components/landing/how-it-works-section.tsx` (flujo común: cargas a tu gente y tus centros →
  Humetría invita y recolecta → recibes diagnóstico, plan y evidencia)
- Modify: `resources/js/components/landing/compliance-section.tsx`, `compliance-points.tsx`. El grupo `privacy` usa
  los textos precisos del spec. El grupo `norm` pasa a "Hecho con la norma en la mano", con una fila por herramienta
  y su fundamento (NOM-035 num. 7.7; NOM-037 lista de verificación y política; LFT arts. 132, 133 y 423 reglamento
  interior), sin repetir las viñetas de las tarjetas.
- Modify: `resources/js/components/landing/demo-section.tsx` (sin "NOM-035"; habla de las herramientas que te
  interesan)
- Test: `tests/Browser/LandingTest.php`

- [ ] **Step 1: Tests que fallan.**
  - `it('speaks to consultants, multi-site companies and corporate groups')`: `assertSee` de tres frases clave que
    elijas, por ejemplo "varias empresas", "varios centros de trabajo" y "varias razones sociales".
  - `it('only names NOM-035 next to the other two tools')`: con `assertScript`, el texto de `main` fuera de
    `[data-test="tool-card"]` y del bloque de cumplimiento no contiene "NOM-035" sin "NOM-037" en la misma sección.
    Implementación sugerida: para cada `section` de `main`, `!t.includes('NOM-035') || t.includes('NOM-037')`.
  - `it('never promises anonymity for identified records')`: el bloque de privacidad contiene "Guía I" y "NOM-037"
    junto a "roles autorizados".
- [ ] **Step 2:** FALLA.
- [ ] **Step 3: Implementa** los textos dentro de los límites.
- [ ] **Step 4:** PASA; `PagesTest` sobre la landing.
- [ ] **Step 5:** commit "Rewrite the landing sections for a multi-tool HR platform".

### Task 8: Menú de la app con grupo "Cumplimiento"

**Files:**
- Modify: `resources/js/lib/app-navigation.ts` (se agrega `export const complianceNavItems: NavItem[] = [];`, con un
  comentario de una línea: aquí entran NOM-035, NOM-037 y Ley Silla al tener su primera página)
- Modify: `resources/js/components/app-sidebar.tsx` (`<NavMain items={mainNavItems} />` sin etiqueta;
  `{complianceNavItems.length > 0 && <NavMain label="Cumplimiento" … />}`)
- Modify: `resources/js/components/nav-main.tsx`, solo si `label` no es opcional (que `label?: string` no pinte el
  `SidebarGroupLabel`)
- Modify: la paleta ⌘K, si arma grupos con etiqueta: que use las mismas listas y omita los grupos vacíos
- Test: `tests/Browser/SmokeTest.php` o `PagesTest.php`, donde viva la prueba del panel

- [ ] **Step 1: Test que falla.** `it('labels no sidebar group after a single tool')`: con un usuario autenticado en
  `/admin`, el texto de `[data-sidebar="group-label"]` no contiene "NOM-035" y el sidebar no muestra "Cumplimiento"
  mientras esté vacío.
- [ ] **Step 2:** FALLA (hoy dice "NOM-035").
- [ ] **Step 3: Implementa.**
- [ ] **Step 4:** PASA; `npm run test:js` si se tocó algo en `lib`.
- [ ] **Step 5:** commit "Group the panel menu by area, ready for the compliance tools".

### Task 9: Correos

**Files:**
- Modify: `resources/views/vendor/mail/html/message.blade.php:33`, `resources/views/vendor/mail/text/message.blade.php:20`
- Modify: `app/Modules/Tenancy/Notifications/OwnerInvitation.php:37`
- Test: `tests/Feature/MailThemeTest.php:22`, `tests/Feature/Tenancy/InviteOwnerTest.php:72`

- [ ] **Step 1: Tests que fallan.**
  - `MailThemeTest` espera el pie `'Enviado por Humetría, herramientas para Recursos Humanos.'` en html y texto, y que
    no contenga "NOM-035".
  - `InviteOwnerTest` espera `introLines` igual a `['Vas a administrar su cuenta, desde donde RH lleva la NOM-035, la
    NOM-037 y la Ley Silla de su personal.']`.
- [ ] **Step 2:** `php artisan test --compact --filter='MailTheme|InviteOwner'`. FALLA.
- [ ] **Step 3: Implementa** los tres textos.
- [ ] **Step 4:** PASA.
- [ ] **Step 5:** commit "Sign every email as Humetría, HR tools, not as a NOM-035 product".

### Task 10: Invariante de privacidad para Ley Silla y verificación final

**Files:**
- Modify: `CLAUDE.md`, sección "Privacidad (invariante)"
- Modify: `app/Modules/Demos/Enums/Workforce.php`, solo el docblock si conviene (opcional)

- [ ] **Step 1:** En CLAUDE.md, la primera viñeta de Privacidad queda así: "Las respuestas de Guía II/III y de la
  encuesta de Ley Silla viven en tablas **sin ninguna llave** hacia empleado, invitación ni token." Se agrega a la
  viñeta del mínimo: "aplica también a la encuesta de Ley Silla". Es solo documentación; no hay código que la ejerza
  todavía.
- [ ] **Step 2:** commit "Extend the anonymity invariant to the Ley Silla survey".
- [ ] **Step 3: Verificación completa.**
  - `composer test`: verde, cobertura ≥ 90 %.
  - `composer test:mutate`: score ≥ 85 %.
  - `npm run types:check`, `npm run test:js` y `npm run check`: verdes.
  - `npm run build && composer test:browser`: verde.
  - `git grep -n "NOM-035" resources app | grep -v -e "NOM-037"` debe listar solo lugares legítimos: la tarjeta de
    NOM-035, el docblock de `Workforce` y el dataset de `Tool`.
- [ ] **Step 4:** revisa visualmente la landing en claro y oscuro contra `docs/design-system/capturas/` (Playwright,
  capturas de móvil y escritorio).
