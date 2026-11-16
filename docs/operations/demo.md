# Manual de operación: la empresa demo

Para quien vende con la demo y para quien la opera. Se lee en cinco minutos: quien presenta puede parar al terminar
§5; lo que va bajo «Para quien opera» es de ingeniería. El diseño está en
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

## 2. Antes de una sesión

- [ ] **Reiniciar demo** a lo más **2 días** antes. La sala marca la demo como vieja pasados 2 días. Lo que envejece:
  - una denuncia recibida «ayer» queda vencida a los ~2 días, y las dos en revisión (recibidas hace 20 y 14 días) pasan
    su plazo de 30 días a los 10 y 16 días;
  - las solicitudes de vacaciones pendientes se escalan: las pedidas «ayer» a los ~3 días hábiles, las de «hoy» a
    los ~4;
  - el pulso abierto cierra a los ~9 días y la evaluación NOM-035 abierta a los ~18;
  - la asistencia de «la semana pasada» deja de serlo a la semana.
- [ ] Reiniciar también **rellena la puerta** y la **cierra**: hay que abrirla otra vez. Cada trabajador de la puerta se
  entrega una sola vez hasta el siguiente reinicio, y recibe ids nuevos en cada preparación: el enlace, la sesión y la
  llave que se llevó un público anterior dejan de servir (ver §3).
- [ ] **Revisar el buzón de denuncias.** El QR del canal no cambia entre reinicios: alguien de un público anterior puede
  seguir mandando denuncias a la demo.
- [ ] Esperar a que la sala diga **Preparada**. Mientras corre muestra **Preparando** y el paso en que va; se puede
  cerrar la página. Tarda alrededor de medio minuto (unos 25 s medidos fuera de pruebas) y unos minutos si la cola está
  ocupada; el job se corta a los 10 minutos.
- [ ] Si se rotó `APP_KEY` desde la última preparación: reiniciar (ver §6).

## 3. La sesión

1. **Entrar como dueño** o **como RH** desde la sala (impersonación; para volver, «Salir»). En la demo se puede abrir
   Compensación y Humi aunque se esté impersonando.
2. **Abrir sala (2 h)**. El QR de la puerta se muestra **una sola vez**: proyectarlo en ese momento. Abrir otra vez cierra
   la sesión anterior y su QR deja de servir: si se pierde el QR, cerrar y abrir la sala. «Cerrar sala» la cierra antes
   de las 2 h.
3. Cada persona del público escanea y recibe **un trabajador demo propio** (hay 40 reservados, repartidos en los 4
   centros). La puerta le da un enlace de un solo uso (WorkerAccess, el que RH emite, a nombre de la jefa de RH de la
   demo): pulsa «Continuar», se le pide **crear su llave de acceso** en su teléfono (huella, rostro o PIN) y entra a su
   portal personal (`/me/{slug}`). Una cookie le devuelve el mismo trabajador si vuelve a escanear (con un enlace nuevo:
   el anterior ya se gastó o deja de servir). Cada trabajador se
   entrega **una sola vez hasta el siguiente reinicio**: no hay «liberar». Sin trabajadores libres la puerta dice
   «La sala está llena»: reiniciar. La sala muestra cuántos quedan libres.
4. Qué hace el público desde su portal. Lo delicado (confirmar la política, el puesto de pie o un documento de
   teletrabajo, el mensaje de apoyo) pide una prueba reciente: la da **con la llave que creó** al entrar. Quien no creó
   llave no puede: el código por correo nunca llega, porque el correo de la demo no sale.
   - contestar el **pulso** abierto o la **evaluación NOM-035** abierta (el avance sube en vivo);
   - **confirmar la política** publicada (sus acuses están pendientes a propósito);
   - poner una **denuncia** en el canal de la demo (QR en la sala). El canal de la demo acepta 60 envíos por hora y 120
     al día por dirección (un salón comparte la Wi-Fi); el tope del canal, 200 al día, sigue (lo que pase de ahí se
     recibe y se retiene al día siguiente);
   - **marcar asistencia en vivo**: la demo no trae llaves ni aceptaciones del acuerdo sembradas. Cada quien marca con la
     llave que creó al entrar, acepta el acuerdo la primera vez y marca frente a la pantalla del checador de Oficinas
     Querétaro. El enlace de esa pantalla se emite en
     la sala, se muestra una vez y deja el checador sin activar hasta que se abre en la tableta o el proyector. Una vez
     que la pantalla muestra su código no se emite otro: para otro enlace, reiniciar.
5. **Seguimiento de una denuncia:** las claves de las denuncias sembradas no se guardan; para enseñar el seguimiento,
   poner una en vivo y usar su clave.

## 4. Qué enseñar y qué evitar

- **Evitar «Por jefe» en los pulsos.** Lista grupos por jefe y cada uno enlaza a un detalle que responde 404 (bug
  conocido de Engagement, pendiente de su propio arreglo).
- **Ley Silla (Oficinas Querétaro):** cinco puntos quedan en «Falta» a propósito, para completarlos en vivo: R2
  (evaluación vigente de cada persona que trabaja de pie), R3 (acta de cada evaluación firmada), R8 (confirmación de cada
  persona evaluada: una persona de la puerta confirma en vivo), R10 (reglamento interior firmado, depositado y repartido) y
  R12 (diagnóstico y programa de seguridad y salud). R7 (medida para cada evaluación Media o Alta) queda en aviso.
- **Humi está vivo:** cada pregunta es una llamada real al modelo, cuesta y llega a PostHog como `$ai_generation`.
- **Acuses de la política: 77 %.** No es un error: las 40 personas de la puerta quedan pendientes.

## 5. Lo que nunca sale

- **Correo:** ninguno. Todas las personas de la demo usan `@demo.humetria.test` y un listener cancela todo mensaje cuyos
  destinatarios son todos de ese dominio (`demo.mail_blocked`, nivel debug: no llega a PostHog con
  `LOG_POSTHOG_LEVEL=info`). Cada preparación encola y cancela del orden de 1 455 avisos.
- La demo no cuenta en cobros, métricas ni conteos de la consola, y los resúmenes y recordatorios programados se la
  saltan.
- Toda página de la demo lleva la marca **DEMO**.

## Para quien opera

Lo que sigue es de ingeniería: cómo corre la preparación, qué excepciones de seguridad tiene y qué revisar si algo falla.

**Cómo corre:** `PrepareDemo` o `ResetDemo` en la cola `demo` de la conexión `redis-long`, que atiende el supervisor
`supervisor-demo` de Horizon, uno a la vez. Nunca corren dos: un segundo clic responde «Ya hay una preparación corriendo».
Si la cola no acepta el job (Redis caído), la corrida falla en ese momento y la sala dice «No se pudo encolar la
preparación». Una corrida que se queda en «running» más de 30 minutos (un worker que murió) se da por abandonada y la
siguiente toma su lugar (`demo.run_abandoned`); si su job llega tarde, ya no hace nada. Un fallo queda en la sala («La
última preparación falló en …» con la clase del error) y en el log `demo.run_failed` (nivel error), solo con la clase,
nunca el mensaje. Preparar, reiniciar, abrir y cerrar la puerta y emitir el enlace del checador dejan su entrada en la
bitácora (`demo.prepare_started`, `demo.reset_started`, `demo.door_opened`, `demo.door_closed`, `demo.kiosk_reissued`),
solo con ids.

## 6. Notas de seguridad

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

## 7. Si algo falla

| Síntoma | Causa probable | Qué hacer |
|---------|----------------|-----------|
| La sala dice «Preparando» y no avanza | Horizon no corre `supervisor-demo` | Revisar Horizon; a los 30 minutos la corrida se da por abandonada y se puede volver a pulsar |
| «La última preparación falló en …» | Error en un paso (`demo.run_failed`) | Buscar en Logs `demo.run_failed` por su `run_id` y clase; volver a pulsar Reiniciar |
| «Ya hay una preparación corriendo» | Otra corrida en curso | Esperar a que termine |
| La puerta dice «La sala está llena» | Se entregaron los 40 trabajadores | Reiniciar demo y abrir la sala otra vez |
| El QR de la puerta no deja entrar | Sala cerrada, vencida (2 h) o reabierta | Abrir sala y proyectar el QR nuevo |
| El QR de denuncias da error | `APP_KEY` rotada o código rotado en la demo | Reiniciar demo |
| No se puede emitir el enlace del checador | La pantalla ya muestra su código | Usar esa pantalla, o reiniciar demo |
| «Por jefe» da 404 | Bug conocido (§4) | No abrirlo en la sesión |
