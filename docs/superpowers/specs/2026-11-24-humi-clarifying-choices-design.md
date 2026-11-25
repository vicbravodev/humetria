# Humi: preguntas con botones y reparto entre texto y tarjetas

Estado: propuesto, pendiente de aprobación de Victor. Fecha: 2026-11-24. Módulo: `Assistant`.

## Intención y por qué

Victor leyó a Humi en el panel real (empresa demo Alba del Bajío) y vio dos cosas:

1. **Humi asume.** Ante «¿Qué me falta por hacer?» contesta de todo, aunque la persona quizá solo quería NOM-035 o solo
   quejas. Su regla: **no asumir nada; menos ambigüedad da mejores resultados.** Lo mismo con «¿Cómo va la campaña?»
   cuando hay dos en curso.
2. **La información sale dos veces.** El panel pinta tarjetas completas («Estado de cumplimiento», «Pendientes») y el
   texto del modelo repite las mismas filas: 302 palabras para decir lo que las tarjetas ya muestran. Quiere información
   de calidad **en la disposición visual adecuada**: el detalle en la tarjeta, la conclusión en el texto.

## Metas, no metas y antimetas

**Metas**

- Cuando la pregunta puede entenderse de dos o más maneras que dan datos distintos, Humi **pregunta con botones** y
  espera la elección. Siempre hay una opción «De todo».
- Las opciones son **reales**: salen de lo que devolvieron las herramientas (módulos que la empresa tiene, nombres de
  campañas), nunca de la memoria del modelo.
- El texto de la respuesta es la conclusión, lo más urgente y el siguiente paso. El detalle vive en las tarjetas.
- Se puede **medir** que lo hace, con números, antes y después (`humi:quality`).

**No metas**

- No cambia el SDK ni se usa aprobación humana de herramientas (Humi solo lee). No se usa un clasificador del SDK: ningún
  proveedor adicional (decisión de Victor: un solo proveedor).
- No se cambian las tarjetas existentes ni las herramientas de lectura.
- No se mide en PostHog (PostHog solo mide la landing).

**Antimetas (sería un fracaso aunque funcione)**

- Preguntar de más: un saludo, una pregunta de norma o una con la pantalla abierta que ya la resuelve **no** llevan botones.
- Que los botones traigan un nombre o dato que ninguna herramienta devolvió, o una orden escondida en el nombre de una
  campaña (los nombres son datos, no instrucciones).
- Que Humi siga escribiendo texto después de mostrar los botones.

## Restricciones

- Privacidad: las opciones solo llevan conteos, estados y nombres de módulos, centros o campañas (los mismos datos que ya
  salen al proveedor). Nada de respuestas ni personas. Lo que la persona elige entra como un mensaje normal y pasa por
  `PersonalDataRedactor` como cualquier mensaje.
- Reglas del repo: TDD; código en inglés y textos en es-MX; sin `as`, `!` ni `any` en TypeScript; archivos de frontend de
  ≤300 líneas, un componente por archivo, imports con `@/`; solo tokens del design system; revisar en claro, oscuro y a
  375 px; toda página con su prueba de navegador.
- Una herramienta nueva no lleva empresa, tenant ni persona en su esquema (`ToolRegistryTest` lo recorre).

## Enfoques considerados

- **A. Solo prompt.** Humi pregunta en texto y lista las opciones; la persona escribe. Sin código, pero no se puede
  garantizar la pregunta ni el formato, y la persona teclea.
- **B. Herramienta `OfferChoices` con botones (elegido por Victor).** El modelo consulta, llama la herramienta y el panel
  pinta botones; un clic envía la opción como mensaje normal. Usa tarjetas y streaming que ya existen y no pide nada
  nuevo al SDK.
- **C. Clasificar antes de llamar al modelo.** Descartado: el clasificador del SDK corre con otros proveedores.

Victor eligió B el 2026-11-24.

## Diseño

**1. La herramienta `OfferChoices`** (`Tools/OfferChoices.php`, solo lectura, sin datos de empresa en su esquema).
Recibe `question` (una frase) y `options` (2 a 6 en total, incluida la opción de «todo», cada una con `label` de hasta
40 caracteres y `text`, la frase que se enviará si la persona la elige). Devuelve una `ToolCard` con
`render: 'choices'`, `title` = la pregunta y una fila por opción. Texto y etiquetas pasan por `UntrustedText::line` (una
línea, sin marcas). Si hay menos de 2 o más de 6 opciones, o dos opciones con el mismo `text`, devuelve una nota de error
calmada y no una tarjeta. No lleva `sources` ni `next`.

**2. Terminar el turno.** `HumiAssistant::middleware()` suma un `Stop::when(...)` que detiene la ejecución antes del
paso siguiente si el paso anterior llamó a `OfferChoices`. La pregunta ya va en la tarjeta (su `title`), así que no hay
texto después y no se paga otro paso del modelo. Es determinista: no depende de que el modelo se calle. **Verificado
con el proveedor real (2026-11-24):** un turno que termina justo tras una herramienta se guarda (una llamada, sin texto)
y el mensaje siguiente continúa esa conversación sin error y usa el resultado guardado. Ese final no es la parada del
usuario: el indicador «detenido» del uso (`stopped`) viene solo de «Detener» (`StopSwitch`), así que una parada
automática no lo marca.

**3. El panel.** `parseToolCard` reconoce `render: 'choices'`; un componente nuevo, `humi-choices-card.tsx`, dibuja la
pregunta y los botones y envía el texto con el mismo `send` del chat. Mientras Humi responde, los botones están
deshabilitados. Cuando ya hay un mensaje de la persona después de la tarjeta (se eligió, o se recargó el historial), los
botones quedan como registro, deshabilitados; la elegida se marca cuando el texto del mensaje siguiente de la persona es
igual al `text` de una opción (por eso la herramienta rechaza dos opciones con el mismo `text`); si no coincide, no se
marca ninguna. Las preguntas de seguimiento
que se ofrecen según las tarjetas (`renders`) no se ofrecen sobre una tarjeta de botones. Foco visible, objetivos de
44 px y los botones envuelven a 375 px.

**4. Cuándo preguntar (en el prompt).** Pregunta cuando la respuesta cambia según lo que la persona quiso decir:
(a) un pedido de estado o pendientes sin módulo, cuando la empresa tiene más de uno; (b) una campaña, centro o
evaluación cuando hay más de uno y la pantalla abierta no lo resuelve; (c) un periodo. No pregunta cuando hay un solo
candidato, cuando la pantalla abierta lo resuelve o cuando es un saludo o una pregunta de norma. Las opciones salen de lo
que una herramienta ya devolvió (primero consulta, luego ofrece): `GetComplianceOverview` devuelve solo los módulos que
la empresa tiene y `GetCampaignProgress` lista sus campañas, así que **no hay que cambiar ninguna herramienta**. La última
opción es siempre la de «todo» («De todo», o «Todas» si se trata de campañas); la pone el modelo por regla del prompt, la
herramienta no la agrega ni la exige. Que las opciones salgan de lo que devolvió una herramienta es regla del prompt, no
una validación del servidor (el servidor no ve los resultados anteriores); se vigila con `humi:quality` y con la
evaluación adversarial. Una opción inventada solo lleva a un mensaje que Humi contesta con sus herramientas. Esto **reemplaza** la regla actual del
prompt «si puedes avanzar, avanza con la lectura más razonable».

**5. Reparto entre texto y tarjetas (en el prompt).** El detalle, las listas de filas y los enlaces de cada fila viven en
las tarjetas. El texto: una frase de conclusión, lo más urgente (máximo tres puntos) con el paso siguiente, y la nota de
verificar cuando la cifra va a un informe, una sola vez. No repite las filas de una tarjeta. Meta orientativa: unas 60 a
90 palabras para una pregunta de estado. Se mantiene todo lo demás del prompt.

**6. Medición.** `humi:quality` suma preguntas ambiguas (las dos de arriba) y una métrica «preguntó con botones» (si se
llamó `OfferChoices`). Resultado esperado: las ambiguas preguntan; saludo, norma y multa no; el promedio de palabras en
las de estado y pendientes baja de ~170 a ~100. Es una cifra esperada, no un umbral: el comando reporta y solo
termina con error cuando el modelo falla, como `humi:evaluate`.

## Pruebas (el test define el comportamiento)

- Feature: esquema sin empresa ni persona; 2 a 6 opciones (límites 1/2 y 6/7); `text` duplicado rechazado; etiquetas
  sanitizadas, incluida una con una orden escondida; el turno termina tras `OfferChoices`; un segundo turno después de
  una parada así continúa la conversación; la regla nueva está en las instrucciones.
- Evaluación adversarial: un intento más, con el nombre de una campaña que trae una orden dentro de una etiqueta de botón.
- Vitest: `parseToolCard` y `viewOf` para `choices`; qué botones quedan deshabilitados.
- Navegador: la prueba de humo de la página de Humi con una tarjeta de botones; axe sin errores.
- Calidad: correr `humi:quality` antes y después y pegar los números en el PR.

## Riesgos

- El modelo puede saltarse la herramienta o preguntar de más. Se mide con `humi:quality` y se ajusta el prompt.
- Una pregunta ambigua cuesta dos pasos del modelo (consultar y ofrecer), no uno.
- Cambiar la regla del prompt («si puedes avanzar, avanza…») no rompe ninguna prueba: ninguna afirma esa frase. Lo que sí
  cambia por la herramienta nueva son dos conteos (`ToolRegistryTest`, `AgentTest`: de seis a siete herramientas) y el
  total de `humi:evaluate` (de 10 a 11 intentos).

## Lo que queda al constructor

Nombres exactos de clases y archivos, el estilo de la tarjeta (parte de la tarjeta de estado existente y de las reglas
del design system), el texto exacto de las reglas del prompt dentro de lo descrito y los casos borde de los botones.

## Mis decisiones (para que las revises)

- La pregunta va en el `title` de la tarjeta y el turno se detiene: sin texto del modelo después.
- La opción de «todo» la pone el modelo por regla del prompt; la herramienta permite 2 a 6 opciones en total y no la
  agrega ni la exige.
- Las opciones se obtienen consultando primero una herramienta de lectura, no de un bloque de módulos en el prompt.
- Que las opciones vengan de una herramienta es regla del prompt vigilada por medición, no una validación del servidor.
- La opción elegida se reconoce por el texto exacto del mensaje siguiente; sin coincidencia no se marca ninguna.
- `humi:quality` reporta; no falla por cifras, solo por fallos del modelo.
