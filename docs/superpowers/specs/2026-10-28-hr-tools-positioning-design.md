# Humetría como plataforma de herramientas de RH (NOM-035, NOM-037, Ley Silla)

Fecha: 2026-10-28. Estado: borrador para aprobación del usuario.

## Intención

Humetría no es "el software de la NOM-035". Es una plataforma de herramientas para Recursos Humanos. El MVP sale al
mercado solo cuando tenga tres herramientas: **NOM-035** (factores de riesgo psicosocial), **NOM-037** (teletrabajo) y
**Ley Silla** (reforma a la LFT). Después vendrán más (evaluación 360, pulsos de clima, etc.), pero hoy solo esas tres se
pueden mencionar.

Este trabajo hace que todo lo que ve un prospecto o un usuario (landing, formulario de demo, consola de demos, menú
de la app y correos) lo presente así. **No** construye NOM-037 ni Ley Silla: eso viene después de terminar NOM-035.

## Metas

1. La landing presenta las tres herramientas como disponibles, cada una con detalle propio y concreto. No usa
   "próximamente" ni texto vago que sirva para cualquier producto.
2. El formulario de demo pregunta qué herramientas le interesan al prospecto. La consola de demos lo muestra.
3. Los textos compartidos (correos, menú, título y descripción de la página, el perfil del prospecto) hablan de
   Humetría como plataforma de RH y no nombran la NOM-035 como si fuera el producto.
4. El menú de la app queda listo para agrupar herramientas: un grupo "Cumplimiento" donde viven NOM-035, NOM-037 y
   Ley Silla, y donde luego caben otros grupos.

## No-metas

- Construir NOM-037 o Ley Silla (modelos, encuestas, reportes).
- Mencionar 360, pulsos o cualquier otra herramienta en algún texto visible.
- Un catálogo de herramientas configurable o un constructor de cuestionarios (YAGNI).
- Precios, testimonios, otro idioma.

## Anti-metas (sería un fracaso aunque funcione)

- Que la landing siga leyéndose como un producto NOM-035 con dos tarjetas pegadas.
- Prometer en la landing funciones que contradigan la norma o la ley. El detalle legal debe ser exacto (ver fuentes).
- Romper el invariante de privacidad en el texto: solo las respuestas de Guía II/III de la NOM-035 son anónimas.
  La Guía I es identificada. No se dice "todo es anónimo".
- Enlaces muertos en la app (un elemento de menú hacia una página que no existe).

## Restricciones

Todas las de `CLAUDE.md`: TDD, Larastan max, TS strictest, límites del frontend (300 líneas por archivo, 100 por
componente, un componente por archivo), tokens del design system, CSP con nonce, textos es-MX (la landing tutea),
`visitPage()` por página, cobertura ≥ 90 %, un solo PR al final (no se abre hasta que el usuario diga "crea el PR").

## Hechos legales (para que el texto sea exacto)

- **NOM-035-STPS-2018** (DOF 23/10/2018): lo que ya está en `CLAUDE.md` y en la landing actual.
- **NOM-037-STPS-2023, Teletrabajo – Condiciones de seguridad y salud en el trabajo** (DOF 08/06/2023, vigente desde
  diciembre de 2023). Obligaciones del patrón: política de teletrabajo por escrito (comunicación contra el aislamiento,
  horarios con descansos y desconexión, aviso de cambio de domicilio); listado actualizado de personas
  teletrabajadoras (lugar convenido, equipo de cómputo y ergonómico entregado, etc.); lista de verificación inicial y
  periódicas de las condiciones de seguridad y salud del lugar de teletrabajo, validada por el patrón; informar sobre
  riesgos ergonómicos y psicosociales y capacitar.
- **Ley Silla**: decreto que reforma los arts. 132, 133, 422 y 423 de la LFT (DOF 19/12/2024, vigente desde el
  17/06/2025). Asientos o sillas con respaldo suficientes para todas las personas trabajadoras, para trabajar y para
  descansos periódicos, en lugares específicos (art. 132 fr. V). Se prohíbe obligar a estar de pie toda la jornada
  (art. 133 fr. XVII Bis). El reglamento interior de trabajo fija los periodos de reposo y las reglas de uso de los
  asientos (arts. 422 y 423).

Fuentes: EY Law Flash Ley Silla y NOM-037, IDConline, Littler, Garrigues (consultadas 2026-10-28). El texto de la
landing no cita montos de multas (las fuentes no coinciden).

## Qué hace Humetría con cada herramienta (lo que promete la landing)

Esto define el alcance de las futuras herramientas. **El usuario debe confirmarlo al aprobar este documento.**

**NOM-035 · Factores de riesgo psicosocial** (lo que ya dice la landing)
- Elige la guía de cada centro de trabajo: hasta 15 trabajadores, solo Guía I; de 16 a 50, Guías I y II; más de 50,
  Guías I y III.
- Calcula la muestra representativa y la sortea en proporción por género.
- Invita a cada persona a contestar desde su celular. Las respuestas de Guías II y III son confidenciales y los
  resultados solo se ven por grupo.
- Señala quién requiere valoración clínica según la Guía I y lo muestra solo a roles autorizados.
- Genera el informe del numeral 7.7.

**NOM-037 · Teletrabajo**
- Lleva el listado de las personas que teletrabajan: lugar convenido y el equipo que se les entregó.
- Cada persona contesta desde su celular la lista de verificación de su lugar de trabajo, la inicial y las
  periódicas. RH la revisa y la valida.
- Avisa cuando toca una verificación periódica.
- Ayuda a escribir la política de teletrabajo y registra que cada persona la recibió.
- Deja la evidencia ordenada para una inspección.

**Ley Silla · Asientos y descansos**
- Registra, por centro de trabajo, qué puestos se hacen de pie y con qué asientos cuentan, para trabajar y para
  descansar.
- Pregunta al personal, de forma confidencial, si de verdad tiene asiento y descansos.
- Propone el texto del reglamento interior con los periodos de reposo y las reglas de uso de los asientos.
- Arma un plan de acción con lo que falta y deja la evidencia de cumplimiento.

## Cambios

### 1. Landing (`resources/js/pages/landing.tsx` y `components/landing/*`)

El orden de las secciones queda así: Header → Hero → **Herramientas** (nueva) → Audiencia → Cómo funciona →
Privacidad y cumplimiento → Demo → Footer.

- **Head:** el título es "Herramientas de RH para cumplir con la STPS" o similar (el texto final queda a criterio del
  builder). La meta description nombra las tres herramientas.
- **Header:** agregar el ancla "Herramientas" (`#herramientas`) junto a las que ya existen.
- **Hero:**
  - La etiqueta (eyebrow) dice "NOM-035 · NOM-037 · Ley Silla".
  - El H1 habla de RH, por ejemplo "Cumple con la STPS y cuida a tu gente, sin complicarte".
  - El párrafo explica que Humetría invita a tu gente, recolecta y te entrega lo que la autoridad pide, por empresa
    y por centro de trabajo.
  - `HeroPreview` deja de ser una sola campaña NOM-035. Pasa a mostrar el tablero de un centro de trabajo con el
    estado de las tres herramientas, por ejemplo:
    - NOM-035: Guía III en curso, 68 de 92;
    - NOM-037: 2 verificaciones pendientes;
    - Ley Silla: plan de acción, 3 de 5 puntos.
  - Debe seguir sin mostrar respuestas individuales y respetar el mínimo por grupo si muestra grupos.
- **Herramientas (nueva):** `id="herramientas"` y una tarjeta por herramienta, al mismo nivel visual. Cada tarjeta
  lleva:
  - el nombre corto y el oficial ("NOM-035-STPS-2018 · Factores de riesgo psicosocial");
  - una línea de "a quién aplica" (NOM-035: a todo centro de trabajo; NOM-037: a quien tenga personas teletrabajando
    más del 40 % de su jornada; Ley Silla: a todo patrón);
  - las viñetas de la sección anterior.

  Un componente por tarjeta, o un componente de tarjeta con los datos en un arreglo, a criterio del builder dentro de
  los límites. Al pie va una línea sobria: "Humetría reúne en un solo lugar las obligaciones de RH ante la STPS".
  Sin promesas de herramientas futuras.
- **Audiencia:** tres perfiles concretos:
  - consultor que atiende a varias empresas, cada una con su espacio;
  - empresa con varios centros de trabajo, cada uno evaluado como pide la norma;
  - grupo con varias razones sociales, todas con la misma cuenta.

  El texto actual que menciona la NOM-035 se reescribe.
- **Cómo funciona:** los pasos describen el flujo común a las tres herramientas: cargas a tu gente y tus centros,
  Humetría invita y recolecta, tú recibes el diagnóstico, el plan y la evidencia.
- **Privacidad y cumplimiento (`compliance-section` / `compliance-points`):**
  - El grupo de privacidad se precisa: las respuestas de Guías II y III y la encuesta de Ley Silla no llevan nombre;
    los resultados solo se ven por grupo; la Guía I y las listas de verificación de NOM-037 son identificadas y solo
    las ven roles autorizados.
  - El grupo "Lo que pide la NOM-035" se sustituye por "Hecho con la norma en la mano": una fila por herramienta con
    su fundamento (NOM-035 num. 7.7, NOM-037, LFT arts. 132 y 133), o se elimina si la sección Herramientas ya lo
    cubre. El builder decide sin duplicar contenido.
- **Demo (`demo-section`):** el texto ya no dice "NOM-035"; habla de las herramientas que te interesan.
- **Formulario de demo:**
  - La pregunta de perfil pasa de "¿Cómo aplicas la NOM-035?" a "¿Para quién es Humetría?":
    - "Para mi empresa o grupo de empresas" (`company`);
    - "Soy consultor y atiendo a varias empresas" (`consultant`).
  - Campo nuevo **"¿Qué herramientas te interesan?"**: tres casillas (NOM-035, NOM-037, Ley Silla), al menos una
    obligatoria (decisión del builder; ver "Decisiones que tomé").
  - Errores de validación en español, con su atributo en `lang/es/validation.php`.

### 2. Demos (backend + consola)

- Enum nuevo `App\Modules\Demos\Enums\Tool`: `Nom035 = 'nom_035'`, `Nom037 = 'nom_037'`, `LeySilla = 'ley_silla'`,
  con `label()` ("NOM-035", "NOM-037", "Ley Silla"). Vive en Demos porque hoy solo Demos lo usa. Cuando otro módulo lo
  necesite, se mueve a un módulo propio con su contrato. No antes.
- Migración que agrega a `demo_requests` la columna `tools jsonb not null` con un `check`: es un arreglo **no vacío**
  y solo contiene valores conocidos. Las filas existentes (solo de desarrollo; no hay producción) se rellenan con
  `["nom_035"]`, que era lo único que se ofrecía cuando se pidieron.
- `StoreDemoRequestRequest`: `tools` es un arreglo requerido, `min:1`, cada elemento `Rule::enum(Tool::class)`,
  `distinct`. `DemoRequestData` lleva `list<Tool>`. El modelo castea `tools` como colección de `Tool`
  (`AsEnumCollection`).
- La consola de demos muestra las herramientas como badges en cada fila.
- `DemoProfile`: se actualiza el docblock. Sus etiquetas de consola ("Empresa", "Consultor") se quedan.
- `Workforce`: su docblock dice que los rangos siguen los umbrales de la NOM-035. Los rangos y su comentario se quedan
  (siguen siendo los umbrales útiles para dimensionar).

### 3. App (menú)

- En `app-sidebar.tsx`, `NavMain label="NOM-035"` cambia. "Panel" va en un grupo sin etiqueta y se
  agrega en `lib/app-navigation.ts` un grupo `complianceNavItems` con etiqueta "Cumplimiento". Hoy ese grupo está
  vacío y **no se muestra** si no tiene elementos. Ahí entra NOM-035 cuando exista su primera página.
- La paleta ⌘K usa las mismas listas.

### 4. Correos

- El pie de `resources/views/vendor/mail/{html,text}/message.blade.php` pasa a "Enviado por Humetría, herramientas
  para Recursos Humanos." o similar.
- `OwnerInvitation`: la línea "desde donde se aplica la NOM-035 a su personal" se cambia por algo como "desde donde
  RH aplica las evaluaciones y lleva el cumplimiento de NOM-035, NOM-037 y Ley Silla".

### 5. Regla de privacidad

- La encuesta de Ley Silla promete confidencialidad. Por eso, en este PR, la sección "Privacidad" de `CLAUDE.md`
  extiende el invariante: las respuestas de la encuesta de Ley Silla también viven sin llaves hacia empleado,
  invitación ni token, y pasan por el mismo mínimo de respondentes por grupo. Así el módulo futuro nace con la regla.

## Fuera de alcance, anotado

- `layouts/app/app-header-layout.tsx` y `components/app-header/app-header-nav-items.ts` son restos del starter kit
  sin uso (enlaces a "Repositorio" y "Documentación"). No se tocan aquí.
- La landing no es pública hasta que salga el MVP (las tres herramientas construidas), así que describir NOM-037 y
  Ley Silla como disponibles es correcto.

## Pruebas

- **Feature:**
  - `tests/Feature/Demos/*`: guarda `tools`; rechaza vacío, valor desconocido y duplicados; la consola recibe
    `tools` en sus props.
  - Test de la migración o constraint: un insert directo con un valor desconocido falla en la base.
- **Unit:** `Tool::label()`.
- **Browser:**
  - `LandingTest`: ve la sección Herramientas con las tres, envía el formulario marcando herramientas, y ve el error
    si no marca ninguna.
  - `DemoConsoleTest`: ve los badges.
  - Las aserciones de texto viejas ("NOM-035" en hero, etiquetas del formulario) se actualizan porque el usuario
    cambió el comportamiento esperado.
- **Correo:** `MailThemeTest` y el test de la invitación afirman el pie nuevo y que no mencionan la NOM-035 como
  producto.
- **Guarda contra regresión:** un test que recorre los textos compartidos (pie de correo, título de la landing,
  etiquetas del sidebar) y falla si contienen "NOM-035" sin nombrar también las otras dos, o lo que el builder vea
  más simple y robusto. Es opcional; el builder decide si aporta.

## Decisiones que tomé (el usuario debe revisarlas)

1. Las casillas de herramientas son obligatorias (al menos una), también en la base. Si el prospecto no sabe, marca
   todas. Las filas de desarrollo existentes se rellenan con `["nom_035"]`.
2. El grupo "Cumplimiento" del menú no se muestra mientras esté vacío; no hay enlaces "próximamente" en la app.
3. El enum `Tool` vive en Demos hasta que otro módulo lo necesite.
4. El detalle de lo que hará Humetría con NOM-037 y Ley Silla (sección "Qué hace Humetría…") es mi propuesta.
   Al aprobarlo, se vuelve el alcance de esas herramientas.
5. Sin montos de multas en la landing.
6. "Panel" queda en un grupo de menú sin etiqueta.
7. La encuesta de Ley Silla entra al invariante de anonimato en `CLAUDE.md` (sección 5).

## Lo que queda al builder

Redacción final de los textos dentro de lo descrito, composición visual de la sección Herramientas y del nuevo
`HeroPreview` (comparando con `docs/design-system/capturas/` en claro y oscuro), íconos (lucide, ya instalado) y
cómo partir componentes para respetar los límites.
