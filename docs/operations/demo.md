# Manual de operación: la empresa demo

Para quien vende con la demo y para quien la opera. Se lee en diez minutos: quien presenta puede parar al terminar
§6; lo que va bajo «Para quien opera» es de ingeniería. El diseño está en
`docs/superpowers/specs/2026-11-16-demo-company-design.md`.

## 1. Qué es y dónde vive

**Manufacturas Alba del Bajío, S.A. de C.V.**: una empresa ficticia de unas 180 personas en 4 centros (Sucursal
Monterrey, Oficinas Querétaro, Centro de distribución y Planta Querétaro) que ya usa Humetria. Cuenta **una sola
historia** que cruza los módulos: una evaluación NOM-035 de hace unos 6 meses con riesgo alto en el turno nocturno de la
planta, el programa de intervención que siguió, pulsos de clima que mejoran, asistencia con las horas extra de ese turno,
denuncias en estados distintos, vacaciones, compensación, políticas con acuses, Ley Silla en las oficinas y teletrabajo.
Todo se sembró con las acciones reales y con fechas relativas al día en que se preparó.

Se maneja desde la **sala de demostración**, `/admin/demo` («Sala de demostración» en el menú de la consola). Solo el superadmin la ve
(gate `manage-demo`); a cualquier otra persona, soporte incluido, le responde 404. Solo puede existir una empresa demo.

Hay dos maneras de dar la demo: la **sesión guiada** (§2), a la medida de un cliente y con su gente real, y la
**puerta** (§3 y §4), para públicos grandes con los 40 trabajadores ficticios. El diseño de la sesión guiada está en
`docs/superpowers/specs/2026-11-26-demo-guiada-design.md`.

## 2. La sesión guiada

Una sesión muestra solo lo que ese cliente quiere ver, con las personas que están en la sala dadas de alta en vivo: les
llegan de verdad los correos y los WhatsApp, contestan o marcan desde su teléfono, y tu pantalla lo ve llegar. Al
terminar se borra todo.

### Empezar

1. En la sala, tarjeta **Nueva sesión**: el **cliente** (opcional, una línea) y los **recorridos** que quiere ver, en el
   orden que quieras («Subir» / «Bajar»): NOM-035, Asistencia, Clima y pulso, Denuncias, Vacaciones y Humi. **El centro
   y su gente** va siempre primero, sin escogerlo. La tarjeta suma los minutos aproximados.
2. **Empezar sesión** reinicia la demo de cero (unos 30 s; unos minutos si la cola está ocupada). La sala muestra
   **Preparando la sesión** con tres fases: reiniciar la demo, contar la historia de la empresa demo y crear el centro de
   la sesión. Se puede dejar la página. Mientras tanto, las herramientas de la sala esperan.
3. La preparación deja la demo lista para un público real:
   - un **centro nuevo**, «Sala · {cliente}» (o «Sala de demostración» sin cliente), donde das de alta a tu público;
   - el **mínimo por grupo de la demo en 1**: los resultados se ven con 1, 5 o 20 personas. Con 1 el producto advierte
     que un grupo tan chico podría identificar respuestas, y los pasos de resultados del guía lo dicen igual;
   - **WhatsApp encendido** en la demo, con el aviso aceptado, como lo dejaría el dueño en Canales;
   - en ese centro, una evaluación NOM-035 aplica **Guías I y II aunque tenga menos de 16 personas** (solo en la
     demo, solo en el centro de la sesión).
4. Con la sesión **en curso**, la tarjeta dice qué paso sigue y el avance de cada recorrido. **Entrar y seguir el guía**
   te deja como dueño en la página de ese paso, con el guía abierto.

Solo hay una sesión a la vez. Un segundo «Empezar sesión» responde «Ya hay una sesión en curso o una preparación
corriendo: espera a que termine.». Sin sesión, **Reiniciar demo** queda como una tarjeta discreta en **Herramientas de
la sala**: empezar y terminar una sesión ya reinician la demo. Con una sesión abierta (una pestaña vieja, un doble
clic), Preparar y Reiniciar no hacen nada y responden «Hay una sesión en curso: …»: la demo es del público hasta que
**Terminar sesión** la borra.

### El guía

El guía aparece en cada página de la empresa demo **solo** al superadmin que la presenta con una sesión en curso. Nadie
más lo ve.

- Un panel a la derecha (una hoja inferior en el teléfono) con la cara de Humi, «Paso 2 de 6 · NOM-035», el título y
  la etiqueta **Hazlo** (lo haces tú) o **Mira** (lo hace el público). Debajo, **Qué hacer**, **Qué está pasando** (el
  dato vivo: «Llegaron 3 de 5 respuestas») y **Por qué importa** (para decírselo al cliente).
- **Avanza con hechos reales**, no con «Siguiente»: la evaluación se lanzó, alguien contestó, la página de resultados se
  abrió. Pregunta cada 3 s mientras está abierto. Al cumplirse un paso lo celebra en una línea («Listo: «…».») y pasa al
  siguiente.
- **Llévame** abre la página del paso y marca con un contorno el control que hay que usar. Si el control aparece
  después (un menú, una lista que carga), el contorno lo alcanza.
- **Pregúntale a Humi** (en los pasos que traen pregunta) abre Humi con la pregunta escrita, sin enviarla: tú decides
  si la mandas. Es el modelo real (§5).
- **Saltar paso** pasa al siguiente sin cumplirlo. En los pasos **Mira** donde el público contesta (NOM-035, clima), en su
  lugar dice **«Seguir con 3 respuestas»**: sigue con las que ya llegaron, sin esperar al resto. Los pasos de llaves y
  marcas se cumplen con la primera.
- **Se pliega** a su píldora («Guía · 4 de 15», junto a Humi) con el botón de minimizar o, en el teléfono, tocando el
  velo. Con una hoja o un diálogo abiertos se pliega solo, para no tapar el formulario; en el teléfono la píldora sigue
  diciendo el paso.
- **«Primeros pasos» no aparece** durante la sesión: un solo guía a la vez.
- Al terminar todos los recorridos, **Volver a la sala** sale de la empresa demo y te lleva a la sala para terminar la
  sesión.

Los accesos y las llaves (Asistencia y Vacaciones), la encuesta de clima, las denuncias y las solicitudes de vacaciones se
cuentan en **toda la empresa demo desde que empezó la sesión**, no solo en el centro de la sesión: si también abriste la puerta,
la llave o la denuncia de un trabajador ficticio puede cumplir un paso antes de tiempo.

El recorrido de Clima y pulso usa una **encuesta de clima**: el pulso sembrado sigue abierto y no deja crear otro. El
pulso sembrado se puede enseñar aparte.

### Lo que sale por correo y WhatsApp

- Las personas que das de alta en el centro de la sesión reciben **correos y WhatsApp reales** en su correo y su número.
  En la demo los mensajes salen **por todos los canales a la vez** (correo **y** WhatsApp); en una empresa real, solo
  por el primero que sirve. WhatsApp sigue sus reglas de siempre (encendido en la plataforma, número válido, sin baja;
  `docs/operations/whatsapp.md`) y en la demo no aparta cupo ni cuenta en cobros.
- Lo que sale: las invitaciones de la evaluación NOM-035 y de la encuesta de clima (correo y WhatsApp), el acceso al
  portal del paso de Asistencia y el aviso de la decisión de vacaciones (correo).
- **El acceso al portal sale solo por correo** mientras `WHATSAPP_WORKER_INVITE` esté apagada (lo está por omisión). La
  plantilla `worker_invite_v1` espera la aprobación de Meta; aprobada, se enciende la variable y el acceso sale también
  por WhatsApp, sin tocar código.
- Nadie recibe acceso al portal por darlo de alta: solo el paso de Asistencia lo manda, a quien va a marcar desde su
  teléfono. Las encuestas llegan por su enlace.
- Las 180 personas ficticias siguen sin recibir nada (§6).

### Vacaciones en tu computadora

El recorrido de Vacaciones se hace en tu pantalla: en el paso de la llave, el guía pide una **Persona del público**
(del centro de la sesión) y **Abrir su portal** te lleva a su portal en esta misma pestaña, con un enlace de un solo uso
que nunca se muestra. Creas la llave con tu huella, pides los días, y regresas al panel con «Atrás» del navegador (el
«Salir» vive en el panel, no en el portal) para aprobar como RH.

- Abrir su portal **anula el enlace pendiente de esa persona**: si en Asistencia ya le llegó su acceso y aún no lo
  abría, ese enlace deja de servir. Escoge a alguien que no vaya a marcar desde su teléfono.
- Tu navegador queda con la **sesión de trabajador** de esa persona hasta «Terminar sesión», que la borra.

### Terminar

**Terminar sesión**, en la sala, pregunta primero y **borra todo**: el reinicio deja la demo como estaba, sin las
personas que diste de alta (sus correos, teléfonos, respuestas, marcas, llaves y mensajes). Mientras corre, la sala dice
**Terminando la sesión**.

- Una sesión cuya preparación **falló** aparece con **Falló**: nadie del público llegó a ella. «Terminar sesión»
  también la cierra y reinicia la demo, para dejarla limpia para la siguiente.
- **Respaldo de 24 h:** cada hora `demo:end-stale-session` termina la sesión abierta hace más de 24 h, como «Terminar
  sesión» (ver «Para quien opera»).
- **«Salir»** del banner, desde la empresa demo, también te regresa a la sala (no a la lista de empresas).

### Si algo falla en la sesión

| Qué ves | Qué hacer | Qué log buscar |
|---------|-----------|----------------|
| «Ya hay una sesión en curso o una preparación corriendo: espera a que termine.» | Esperar a que la sala diga cómo va; si hay una sesión en curso, terminarla | `demo.run_started`, `demo.session_started` |
| «No se pudo encolar la preparación: inténtalo de nuevo en unos minutos.» | Redis o Horizon caídos: revisarlos y volver a pulsar | — (la corrida falla en ese momento) |
| «Preparando la sesión» no avanza | Revisar que Horizon corra `supervisor-demo`. A los 30 minutos el respaldo la marca fallida | `demo.run_step`, `demo.run_abandoned`, `demo.session_failed` |
| La sesión dice **Falló** | «Terminar sesión» y empezar otra | `demo.run_failed` (clase y paso), `demo.session_failed` (aviso) |
| «Terminando la sesión» no avanza | Revisar Horizon. El respaldo vuelve a correr el reinicio cada hora | `demo.session_ending`, `demo.run_failed` |
| «Hay una preparación corriendo: espera a que termine para terminar la sesión.» | Esperar y volver a pulsar | `demo.session_ending_undone` |
| El guía no aparece | Entrar con **Entrar y seguir el guía** desde la sala, con la sesión en curso (no preparándose) | `demo.session_ready` |
| Un paso no avanza | El hecho no ha pasado (¿llegó el correo? ¿abriste la página con «Llévame»?). Pulsar «Seguir con N…» o «Saltar paso» | `demo.session_guide_moved`, `demo.session_page_visited`, `demo.session_step_skipped` |
| No llega el WhatsApp del acceso al portal | Es lo esperado mientras `WHATSAPP_WORKER_INVITE` esté apagada: sale por correo | — |
| No llega ningún WhatsApp | Número no válido, la persona se dio de baja o WhatsApp pausado en la plataforma | los de `docs/operations/whatsapp.md` |
| El enlace de acceso de una persona ya no sirve | Abriste su portal en tu computadora (Vacaciones): reemitirle el acceso | `demo.portal_link_issued` |
| La persona no está en «Persona del público» | No está activa en el centro de la sesión | — |

## 3. Antes de abrir la puerta

Para la puerta sin sesión guiada. Con una sesión no hace falta: empezarla ya reinicia la demo.

- [ ] **Reiniciar demo** a lo más **2 días** antes. La sala marca la demo como vieja pasados 2 días. Lo que envejece:
  - una denuncia recibida «ayer» queda vencida a los ~2 días, y las dos en revisión (recibidas hace 20 y 14 días) pasan
    su plazo de 30 días a los 10 y 16 días;
  - las solicitudes de vacaciones pendientes se escalan: las pedidas «ayer» a los ~3 días hábiles, las de «hoy» a
    los ~4;
  - el pulso abierto cierra a los ~9 días y la evaluación NOM-035 abierta a los ~18;
  - la asistencia de «la semana pasada» deja de serlo a la semana.
- [ ] Reiniciar también **rellena la puerta** y la **cierra**: hay que abrirla otra vez. Cada trabajador de la puerta se
  entrega una sola vez hasta el siguiente reinicio, y recibe ids nuevos en cada preparación: el enlace, la sesión y la
  llave que se llevó un público anterior dejan de servir (ver §4).
- [ ] **Revisar el buzón de denuncias.** El QR del canal no cambia entre reinicios: alguien de un público anterior puede
  seguir mandando denuncias a la demo.
- [ ] Esperar a que la sala diga **Preparada**. Mientras corre muestra **Preparando** y el paso en que va; se puede
  cerrar la página. Tarda alrededor de medio minuto (unos 25 s medidos fuera de pruebas) y unos minutos si la cola está
  ocupada; el job se corta a los 10 minutos.
- [ ] Si se rotó `APP_KEY` desde la última preparación: reiniciar (ver §7).

## 4. La puerta: la sala sin guía

1. **Entrar como dueño** o **como RH** desde la sala (impersonación; para volver, «Salir»). En la demo se puede abrir
   Compensación y Humi aunque se esté impersonando.
2. **Abrir sala (2 h)**. El QR de la puerta se muestra **una sola vez**: proyectarlo en ese momento. Abrir otra vez cierra
   la sesión anterior y su QR deja de servir: si se pierde el QR, cerrar y abrir la sala. «Cerrar sala» la cierra antes
   de las 2 h.
3. Cada persona del público escanea y recibe **un trabajador demo propio** (hay 40 reservados, repartidos en los 4
   centros). La puerta le da un enlace de un solo uso (WorkerAccess, el que RH emite, a nombre de la jefa de RH de la
   demo): pulsa «Continuar», se le pide **crear su llave de acceso** en su teléfono (huella, rostro o PIN) y entra a su
   portal personal (`/me/{slug}`). La pantalla de la llave no tiene «Ahora no»: quien quiera mirar antes, abre
   `/me/{slug}` directamente. El enlace vale 48 h o hasta el siguiente reinicio (sobrevive a la sesión de la sala:
   cerrarla no lo anula). Una cookie le devuelve el mismo trabajador si vuelve a escanear (con un enlace nuevo:
   el anterior ya se gastó o deja de servir). Cada trabajador se
   entrega **una sola vez hasta el siguiente reinicio**: no hay «liberar». Sin trabajadores libres la puerta dice
   «La sala está llena»: reiniciar. La sala muestra cuántos quedan libres.
4. Qué hace el público desde su portal. Lo delicado (confirmar la política, el puesto de pie o un documento de
   teletrabajo, el mensaje de apoyo) pide una prueba reciente: la da **con la llave que creó** al entrar. Quien no creó
   llave no puede: el código por correo nunca llega, porque el correo de las personas ficticias no sale (§6). Es el alta real del producto,
   no un atajo de la demo: **avisar al público** antes de que escanee que se le pedirá crear una llave en su teléfono.
   La llave se queda en su teléfono a nombre de una persona ficticia y no sirve tras un reinicio: puede borrarla.
   - contestar el **pulso** abierto o la **evaluación NOM-035** abierta (el avance sube en vivo);
   - **confirmar la política** publicada (sus acuses están pendientes a propósito);
   - poner una **denuncia** en el canal de la demo (QR en la sala). El canal de la demo acepta 60 envíos por hora y 120
     al día por dirección (un salón comparte la Wi-Fi); el tope del canal, 200 al día, sigue (lo que pase de ahí se
     recibe y se retiene al día siguiente);
   - **marcar asistencia en vivo**: la demo no trae llaves ni aceptaciones del acuerdo sembradas. Cada quien marca con la
     llave que creó al entrar, acepta el acuerdo la primera vez y escanea la pantalla del checador de **su** centro: cada
     uno de los 4 centros tiene la suya (la de Oficinas Querétaro activa desde hace un mes; las otras tres, nuevas). Para
     cada persona, muestre la pantalla de SU centro: el botón «Emitir enlaces de las pantallas» de la sala emite las 4,
     cada una con el nombre de su centro; ábralas en tabletas o lado a lado en el proyector (cada pantalla dice su centro
     arriba; la persona ve el suyo en su portal, en «Avisos y resultados de su centro»). Quien escanee otra pantalla queda «fuera de su centro» (la política de la demo permite y señala): no es un
     error, y también es un buen momento para mostrar esa función. La marca también queda «fuera de zona» o «sin
     ubicación», porque el público no está en el centro: es lo que la política permite y señala. Cada enlace se muestra
     una vez y deja su pantalla sin activar hasta que se abre; una pantalla que ya muestra su código no recibe otro
     enlace (la sala solo emite los de las demás): para otro, reiniciar.
5. **Seguimiento de una denuncia:** las claves de las denuncias sembradas no se guardan; para enseñar el seguimiento,
   poner una en vivo y usar su clave.

## 5. Qué enseñar y qué evitar

- **Evitar «Por jefe» en los pulsos.** Lista grupos por jefe y cada uno enlaza a un detalle que responde 404 (bug
  conocido de Engagement, pendiente de su propio arreglo).
- **Ley Silla (Oficinas Querétaro):** cinco puntos quedan en «Falta» a propósito, para completarlos en vivo: R2
  (evaluación vigente de cada persona que trabaja de pie), R3 (acta de cada evaluación firmada), R8 (confirmación de cada
  persona evaluada: una persona de la puerta confirma en vivo con su llave), R10 (reglamento interior firmado, depositado y repartido) y
  R12 (diagnóstico y programa de seguridad y salud). R7 (medida para cada evaluación Media o Alta) queda en aviso.
- **Humi está vivo:** cada pregunta es una llamada real al modelo, cuesta y llega a PostHog como `$ai_generation`.
- **Acuses de la política: 77 %.** No es un error: las 40 personas de la puerta quedan pendientes.
- **Asistencia, «Teléfonos vinculados 0 de 180».** No es un error: una llave no se puede sembrar (la crea cada teléfono),
  así que la puesta en marcha queda en 3 de 4 pasos y sube con cada persona del público que crea la suya.

## 6. Lo que sale y lo que no

- **Correo de las personas ficticias:** ninguno. Las 180 personas sembradas (las 40 de la puerta incluidas) usan
  `@demo.humetria.test` y un listener cancela todo mensaje cuyos destinatarios son todos de ese dominio
  (`demo.mail_blocked`, nivel debug: no llega a PostHog con `LOG_POSTHOG_LEVEL=info`). Cada preparación encola y cancela
  del orden de 1 455 avisos.
- **Las personas que das de alta en una sesión guiada sí reciben** correos y WhatsApp reales, por todos los canales a la
  vez; el acceso al portal, solo por correo mientras `WHATSAPP_WORKER_INVITE` esté apagada (§2). Sin sesión, la demo no
  tiene a nadie con un correo o un número real.
- La demo no cuenta en cobros, métricas ni conteos de la consola, y los resúmenes y recordatorios programados se la
  saltan.
- Toda página de la demo lleva la marca **DEMO**.

## Para quien opera

Lo que sigue es de ingeniería: cómo corre la preparación, qué excepciones de seguridad tiene y qué revisar si algo falla.

**Cómo corre:** `PrepareDemo`, `ResetDemo` o `PrepareDemoSession` (el reinicio de una sesión, más su centro, el mínimo
en 1 y WhatsApp: el paso «Sala de la sesión») en la cola `demo` de la conexión `redis-long`, que atiende el supervisor
`supervisor-demo` de Horizon, uno a la vez. Nunca corren dos: un segundo clic responde «Ya hay una preparación
corriendo». Si la cola no acepta el job (Redis caído), la corrida falla en ese momento y la sala dice «No se pudo
encolar la preparación». Una corrida que se queda en «running» más de 30 minutos (un worker que murió) se da por
abandonada y la siguiente toma su lugar (`demo.run_abandoned`); si su job llega tarde, ya no hace nada. Un fallo queda
en la sala («La última preparación falló en …» con la clase del error) y en el log `demo.run_failed` (nivel error), solo
con la clase, nunca el mensaje. Preparar, reiniciar, abrir y cerrar la puerta y emitir el enlace del checador dejan su
entrada en la bitácora (`demo.prepare_started`, `demo.reset_started`, `demo.door_opened`, `demo.door_closed`,
`demo.kiosk_reissued`), igual que empezar y terminar una sesión y abrir el portal de una persona desde el guía
(`demo.session_started`, `demo.session_ended`, `demo.portal_link_issued`), solo con ids. Los logs de la sesión van con
`demo.session_*` y solo con ids y llaves de paso, nunca el cliente ni las personas.

**Respaldo de la sesión:** cada hora `demo:end-stale-session` termina la sesión abierta hace más de 24 h, como «Terminar
sesión» (`demo.session_expired`; el reinicio borra lo que dejó el público). Una sesión atorada porque su corrida falló o
murió suelta el candado: preparándose, se marca fallida (también si su corrida terminó sin dejarla lista, como tras un
«Terminar sesión» pulsado mientras se preparaba); terminándose, su reinicio corre de nuevo. Si la demo está
ocupada, lo intenta a la hora siguiente. El comando dice qué hizo: «Sesión vencida: terminando.», «Sesión atorada
preparándose: marcada fallida.», «Sesión atorada terminándose: reinicio de nuevo.», «La demo está ocupada: se intenta de
nuevo en una hora.» o «Sin cambios.».

## 7. Notas de seguridad

- **Excepción a las tablas de solo inserción.** Una migración deja que 14 tablas de solo inserción (respuestas anónimas,
  tamizajes de Guía I, clima, marcas y convenios de asistencia, movimientos de vacaciones, uso, evidencia de difusión,
  evaluaciones de Ley Silla firmadas) **borren filas solo de la empresa con `is_demo`**. UPDATE y TRUNCATE siguen
  bloqueados para todas, la demo incluida. `audit_entries` no se toca: crece un poco con cada reinicio.
- **La marca `is_demo`** se pone al crear la empresa y no cambia: un trigger rechaza cambiarla en cualquier sentido, así
  que ninguna empresa real puede volverse la demo (y quedar borrable) ni la demo dejar de serlo.
- **`APP_KEY`.** Sellan con ella el código del canal de denuncias de la demo (su QR) y los enlaces de las encuestas.
  Los enlaces que reparte la puerta no dependen de ella: se guardan solo como hash y valen una vez. Al rotarla la sala muestra un QR nuevo que solo sirve tras reiniciar, y los textos cifrados viejos solo se
  leen si la llave anterior está en `APP_PREVIOUS_KEYS`. Si alguien pulsa «rotar código» en el canal de la demo, el QR de
  la sala deja de servir hasta el siguiente reinicio.
- **La puerta `demo/{code}`** es pública: el código se guarda solo como hash y se muestra una vez, tiene throttle
  por dirección (`demo-door`) y responde la misma página si el código es desconocido, está cerrado o venció. Solo entrega
  personas activas de la empresa demo.
- **Los enlaces de la puerta** son enlaces de un solo uso de WorkerAccess emitidos como la jefa de RH de la demo: valen
  48 h o hasta el siguiente reinicio, sobreviven a la sesión de la sala (`CloseDoor` no los anula) y solo se emiten dentro
  de la demo para una persona de la puerta. Cada uno deja el log `worker_access.link_created`, pero **ninguna entrada en
  la bitácora**: siendo de solo inserción, crecería con cada escaneo (aceptado). Pulsar «Continuar» es lo único que gasta
  el enlace: la vista previa de un QR no lo quema. Lo delicado pide una prueba reciente, que el visitante solo puede dar
  con una llave que cree en su propio teléfono.

## 8. Si algo falla

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| La sala dice «Preparando» y no avanza | Horizon no corre `supervisor-demo` | Revisar Horizon; a los 30 minutos la corrida se da por abandonada y se puede volver a pulsar |
| «La última preparación falló en …» | Error en un paso (`demo.run_failed`) | Buscar en Logs `demo.run_failed` por su `run_id` y clase; volver a pulsar Reiniciar |
| «Ya hay una preparación corriendo» | Otra corrida en curso | Esperar a que termine |
| La puerta dice «La sala está llena» | Se entregaron los 40 trabajadores | Reiniciar demo y abrir la sala otra vez |
| El QR de la puerta no deja entrar | Sala cerrada, vencida (2 h) o reabierta | Abrir sala y proyectar el QR nuevo |
| El QR de denuncias da error | `APP_KEY` rotada o código rotado en la demo | Reiniciar demo |
| No se pueden emitir los enlaces del checador, o falta el de un centro | Esa pantalla ya muestra su código | Usar esa pantalla, o reiniciar demo |
| Una marca sale «fuera de su centro» | La persona escaneó la pantalla de otro centro | Nada: la marca se tomó. Que escanee la de su centro la siguiente vez |
| «Por jefe» da 404 | Bug conocido (§5) | No abrirlo en la sesión |
| Demasiadas solicitudes (429) al abrir el enlace o crear la llave | Límites de WorkerAccess por dirección: abrir el enlace 30 por minuto, crear la llave 30 por minuto, dar la prueba 60 por minuto. Un salón de ~40 personas detrás de una sola Wi-Fi los alcanza | Pedir que escaneen en tandas de ~10 o con datos móviles. La puerta permite 120 por minuto y el canal de denuncias se subió para la demo; los límites de WorkerAccess no se subieron (son los de seguridad del producto): subirlos es decisión del dueño |
| Pide una prueba o no puede confirmar | El visitante aún no tiene llave | Que cree su llave en su teléfono (§4) |
