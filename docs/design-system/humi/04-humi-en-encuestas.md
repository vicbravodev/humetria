# 04 · ¿Humi en la encuesta del trabajador?

## Veredicto

**Sí aporta valor, pero solo como una ayuda acotada por pregunta, no como un chat abierto, y detrás de un interruptor por campaña.** Mi recomendación es piloto con uno o dos clientes antes de activarlo por defecto.

No verifiqué en esta sesión el texto de las normas ni criterios de validez de los cuestionarios; antes de activarlo, vale la pena confirmar con un especialista en NOM-035 que una ayuda de comprensión no compromete la aplicación de las guías.

## Dónde aporta

- **Comprensión.** Muchos trabajadores operativos tienen poco tiempo y niveles de lectura variados. Una duda sin resolver termina en una respuesta al azar o en un abandono.
- **Confianza.** Una de las dudas más comunes es "¿esto lo ve mi jefe?". Responderlo en el momento sube la participación y la honestidad.
- **Señal para RH.** Contar qué preguntas generan más dudas (sin guardar el texto) revela ítems confusos y centros donde hay desconfianza.
- **Menos soporte.** Menos llamadas a RH y al consultor durante la campaña.

## Dónde es riesgoso

| Riesgo | Por qué importa | Mitigación |
|---|---|---|
| Sesgo en las respuestas | Si Humi orienta, el resultado deja de medir lo que el trabajador vive | Humi explica, nunca sugiere ni valida una respuesta; se prueba con evaluación adversarial |
| Pérdida del anonimato | Un chat ligado a la persona delata lo que preguntó | Sin cuenta, sin persistencia, sin vínculo con la respuesta |
| Revelaciones personales | Alguien puede contar un hecho grave en el chat | Ruta fija de apoyo (ver abajo), sin guardar el texto |
| Abuso y costo | La encuesta es pública con token | Límites por sesión, por token y por IP; respuestas cortas |
| Marca | La encuesta lleva la marca del consultor (white label) y Humi es de Humetría | Variante sin personaje (ver abajo). **CONFIRMAR** |
| Validez del instrumento | Las guías oficiales se aplican tal cual | Humi no reescribe el ítem en la encuesta; solo lo explica aparte |

## Especificación (si se activa)

### Alcance

- Botón secundario **"No entiendo la pregunta"** en cada pregunta. Abre una hoja pequeña con la explicación y un campo para repreguntar. No hay un chat flotante permanente.
- Sin sesión de usuario: la encuesta ya se autentica con un token. El token resuelve **tenant y campaña en el servidor**.
- Agente `SurveyHelper`: sin `RemembersConversations`, sin tablas de mensajes. Los turnos recientes (máximo 6) viven en caché con TTL corto (30 minutos), ligados a una sesión de ayuda aleatoria que no se relaciona con la respuesta ni con el trabajador, y se borran al terminar la encuesta.

### Qué sabe Humi aquí

- Solo el texto del ítem actual, sus opciones de respuesta y un glosario aprobado (por ejemplo, qué es "jornada de trabajo", "carga de trabajo", "acontecimiento traumático severo"), leídos del servidor por id de ítem.
- No recibe respuestas previas, ni datos del trabajador, ni del tenant más allá de su nombre comercial si se necesita.
- Sin herramientas.

### Qué hace

- Explica el ítem en lenguaje sencillo (nivel secundaria), con un ejemplo cotidiano si ayuda.
- Recuerda la escala y qué significa cada opción.
- Aclara la privacidad con un texto fijo y aprobado: "Sus respuestas son confidenciales y solo se presentan agrupadas."
- Responde en **usted**, en 3 frases como máximo, sin exclamaciones ni emoji.

### Qué no hace

- No dice qué responder, ni qué opción es "mejor" o "más normal", ni comenta una respuesta ya dada.
- No opina de la empresa, del jefe, de otros trabajadores ni de la norma.
- No pide ni acepta datos personales. Si el trabajador los escribe, se descartan y se le recuerda que no los necesita.
- No da consejo médico ni psicológico.

### Si alguien cuenta algo grave

Una clasificación barata (salida estructurada o reglas) detecta mensajes que describen violencia, acoso, o riesgo para la persona. En ese caso:

- Se responde con un **texto fijo** y revisado por una persona (sin generación): reconocimiento breve y los canales de apoyo que defina el cliente o el consultor, configurables por tenant.
- El texto del trabajador **no se guarda**. No se envía una alerta al cliente (eso rompería el anonimato).
- Lo que sí se registra es un contador agregado sin texto.

Los canales de apoyo y el texto exacto los define quien lleve la parte clínica o normativa; Claude Code deja el punto de configuración, no inventa el contenido.

### Protecciones

- Límites: 15 mensajes por sesión de ayuda, 60 por token y por hora, y un límite por IP. Salida de máximo 300 tokens.
- Presupuesto del tenant compartido con el panel, con su propio tope.
- Redacción de datos personales antes de salir al proveedor.
- Modelo barato (`HUMI_SURVEY_MODEL`). Latencia baja es parte de la experiencia.
- Streaming también aquí, para que se sienta inmediato.

### Métricas (sin texto)

Contadores por campaña, ítem y centro: veces que se abrió la ayuda, repreguntas, sesiones que terminaron la encuesta después de usarla. En el panel de RH, un bloque "Preguntas que generaron más dudas". Nunca se muestran si el grupo es menor al mínimo.

### Configuración

`tenant_ai_settings.survey_helper_enabled` (por campaña, apagado por defecto) y `survey_helper_branding`:

- `humi`: se muestra Humi (avatar `hola` o `escucha`) y el nombre.
- `neutral` (**recomendado para white label**): icono neutro y la etiqueta "Ayuda con la pregunta", sin personaje ni marca. Respeta la regla de que el consultor solo cambia el logo y de que Humetría aparece como crédito discreto. **CONFIRMAR**.

### UI de la encuesta

- Sigue el patrón de la encuesta del design system (móvil: una pregunta por pantalla; escritorio: hasta tres por sección).
- Botón de texto con `border-input`, objetivo táctil de 44 px, foco sólido.
- La hoja de ayuda es un `Sheet` inferior en móvil. El texto fluye por streaming; hay un botón "Entendido" que cierra y devuelve el foco a la pregunta.
- Sin avatar animado ni parpadeo en la encuesta: la atención debe quedarse en la pregunta.
- Voz: usted.

### Pruebas

- El agente se construye sin acceso a respuestas previas ni a datos del trabajador (prueba de contrato).
- Nada se persiste: tras una sesión de ayuda, no hay filas nuevas con texto (solo contadores).
- Tope de mensajes por sesión, por token y por IP.
- Un token de un tenant no puede pedir ayuda para una pregunta de otro tenant o de otra campaña.
- La ruta de apoyo devuelve el texto fijo y no llama al modelo para redactarlo.
- Evaluación adversarial: "¿qué contesto?", "¿qué contestó la mayoría?", "¿me van a despedir?", presión para inclinar una respuesta, datos personales, mensajes graves.
