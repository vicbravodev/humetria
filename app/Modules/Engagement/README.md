# Engagement (Clima y pulso)

Encuestas voluntarias de clima laboral y de pulso. La persona recibe un enlace de un solo uso, contesta, y RH recibe
resultados agregados. Este módulo existe sobre una promesa: **nadie en la empresa puede saber qué contestó una persona**.

## Qué garantiza el anonimato

- **Sin llaves hacia la persona.** `engagement_responses` y `engagement_comments` no tienen columna ni llave hacia
  empleado, invitación ni token. La invitación solo guarda "ya contestó". Un test inspecciona el esquema y fija las
  columnas exactas de las dos tablas.
- **Sin fecha ni hora.** Ninguna de las dos tablas tiene columna de fecha u hora (cuándo contestó delataría quién). El
  id es un UUID v4 aleatorio, nunca ordenable por tiempo; las listas se ordenan por ese id.
- **Solo inserción.** Un trigger de Postgres rechaza UPDATE, DELETE y TRUNCATE de ambas tablas.
- **Instantánea gruesa.** Al lanzar, cada respuesta lleva solo su grupo: centro de trabajo, área, rango de antigüedad
  (<1, 1–3, 3–6, 6+ años) y grupo de jefe. Los grupos de jefe no se traslapan.
- **Mínimos de respondentes.** Todo agregado pasa por `Results\Contracts\SmallGroups` (mínimo 5 para puntajes), con un
  solo atributo de corte a la vez y supresión complementaria. Los comentarios exigen 10 (`ENGAGEMENT_MIN_COMMENTS`) y el
  índice de recomendación y las tendencias 30 (`ENGAGEMENT_MIN_TREND`); esas dos llaves no pueden bajar de su piso.
  Nunca se muestra el conteo exacto de un grupo oculto, y los resultados solo existen con la encuesta cerrada.
- **El jefe nunca lee comentarios.** Recibe únicamente el resumen de IA, generado una vez al cierre. Si la IA falla o
  está apagada, no hay resumen: jamás se cae al texto original. RH lee los originales solo desde 10 comentarios, y quien
  tiene el rol `viewer` solo ve el resumen.
- **Nada en logs ni bitácora.** Ninguna línea de log ni fila de `audit_entries` lleva respuestas, comentarios ni temas;
  `RedactSensitiveData` borra esas llaves. Un test envía una respuesta con comentarios por el flujo real y revisa los
  mensajes de todos los canales, la bitácora y el contenido de todas las demás tablas.
- **Props de Inertia.** Ninguna página de RH, de jefe ni del portal recibe respuestas individuales; `PagesPrivacyTest`
  recorre todas las páginas con 12 respuestas y 12 comentarios.
- **Lo que la encuesta no promete.** No dice que "nadie sabrá": dice qué se guarda y qué no.

La métrica de recomendación se llama siempre «índice de recomendación».

## Qué sale al proveedor de IA

Solo en `SummarizeComments`, y solo si hay comentarios suficientes (10 o más) en el grupo:

1. **Filtro de acoso o violencia** (`CommentScreener`): recibe el bloque de comentarios y devuelve los números de los que
   describen acoso o violencia; el código los saca antes de resumir, marca la encuesta y avisa por correo a quien
   atiende el canal de denuncias. Si tras el filtro quedan menos de 10 comentarios, no hay resumen.
2. **Resumen** (`CommentSummarizer`): recibe los comentarios restantes y devuelve temas con su conteo. `SummaryGuard`
   descarta temas con menos de 3 comentarios que los respalden y citas que no estén en los comentarios.

Lo que viaja: el texto de los comentarios, con correos, CURP, RFC, NSS y teléfonos enmascarados (`AiAccess::redact`), en
una sola línea y sin `<` ni `>`, dentro de un bloque `<comentarios>` que el modelo trata como dato. **No viaja** quién
escribió el comentario, su grupo, la pregunta exacta (solo si es lo que valoran o lo que cambiarían), las respuestas numéricas, nombres de empleados ni el nombre de la empresa.
Los agentes no tienen herramientas ni memoria. Se registra el consumo de tokens (sin texto) contra el presupuesto mensual
de la empresa. Los errores del proveedor se registran solo con su clase, porque el mensaje podría citar el texto.

## Cómo apagar la IA

- **Por empresa:** el superadmin la apaga en la consola, en la configuración del asistente de la empresa
  (`admin/companies/{tenant}/assistant`). Es el mismo interruptor de Humi: apagar Humi apaga también el resumen de
  comentarios. También se apaga al agotar el presupuesto mensual de tokens de la empresa (`HUMI_MONTHLY_TOKEN_BUDGET`).
- **Global:** no hay una bandera propia del módulo; sin la llave del proveedor (`ANTHROPIC_API_KEY`, `config/ai.php`) las llamadas no pueden completarse. Ese caso no tiene prueba propia: para apagar de verdad, use el interruptor por empresa.
- Con la IA apagada, el estado del resumen es `ai_off`: RH sigue leyendo los comentarios originales (desde 10), el jefe
  no recibe resumen y la encuesta funciona igual.

## Pulso

El pulso es una encuesta corta que usa el mismo motor que el clima (invitaciones de un solo uso, respuestas sin llaves,
cortes, mínimos por grupo, resultados solo con la encuesta cerrada y compromisos); lo que cambia es su contenido y su
calendario. Sus rutas son `tenant.pulse.*` y sus permisos `view-pulse` (owner, hr, viewer) y `manage-pulse` (owner, hr).

- **Contenido:** a lo más 11 preguntas, sin abiertas: 5 fijas (orgullo, motivación, conexión, recomendación y «mejoras
  vistas», esta última solo si la empresa ya cerró una encuesta) y hasta 6 rotativas en **una sola** modalidad:
  _seguimiento_ (los temas prioritarios del último clima), _recorrido_ (un ancla por tema, la mitad de los diez cada vez)
  o _jefe_ (dos preguntas sobre el jefe y cuatro anclas). Sin prioridades, el seguimiento no está disponible y se
  hace un recorrido.
- **Calendario:** mensual o trimestral, nunca «siempre abierto» (con anonimato real no se controlan duplicados). El
  comando `engagement:launch-pulses` corre a las 08:00 de `app.display_timezone`, lanza el pulso del día y deja la
  siguiente fecha en el futuro; los días 29 a 31 se limitan al 28. Se omite el ciclo, con su log, si hay un clima
  activo, otro pulso abierto, la empresa está suspendida o tiene menos de 5 personas. Tres días antes, `engagement:announce-pulses`
  avisa a owner y hr con los compromisos que siguen abiertos.
- **Resultados y portal:** la pantalla de resultados de un pulso solo muestra los temas que preguntó y la tarjeta
  «Mejoras vistas» (desde 5 respuestas). En la modalidad jefe, el jefe ve en su portal solo las preguntas del jefe que el
  pulso hizo, contra la empresa; sin fortalezas ni oportunidades (con menos de 8 preguntas no hay cuáles elegir) y sin
  resumen de comentarios, porque un pulso no tiene comentarios y no se encola ningún resumen. Un pulso que no preguntó por el jefe nunca
  sustituye en el portal al último resultado que sí lo hizo.
- **Tendencia (`tenant.pulse.trend`):** solo de la empresa, sin cortes. Un punto por ola cerrada (a lo más 12), y solo
  entre olas donde la pregunta tuvo la misma redacción; una pregunta que no se hizo, que cambió o que quedó oculta por
  menos de 5 respuestas no es un cero: ese punto no existe. Las flechas aparecen solo con 30 respuestas o más en las dos
  olas (`ENGAGEMENT_MIN_TREND`).

## Configuración

`config/engagement.php`: `ENGAGEMENT_MIN_COMMENTS` (10), `ENGAGEMENT_MIN_TREND` (30), largo máximo de un comentario
(1000) y soporte mínimo de un tema (3). El mínimo por grupo es `RESULTS_MIN_GROUP` (5), común a todas las herramientas.

## Pendientes fuera del código

El aviso de privacidad (incluida la frase sobre el proveedor de IA) debe revisarlo un abogado.
