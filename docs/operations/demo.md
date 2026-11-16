# Manual de operación: la empresa demo

Para quien vende con la demo y para quien la opera. Se lee en cinco minutos. El diseño está en
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
  - una denuncia recibida «ayer» queda vencida a los ~2 días;
  - las solicitudes de vacaciones pendientes se escalan a los ~4 días hábiles;
  - la campaña NOM-035 abierta y el pulso abierto cierran a los ~9 días;
  - la asistencia de «la semana pasada» deja de serlo a la semana.
- [ ] Reiniciar también **rellena la puerta**: cada trabajador de la puerta se entrega una sola vez hasta el siguiente
  reinicio (ver §3).
- [ ] Esperar a que la sala diga **Preparada**. Mientras corre muestra **Preparando** y el paso en que va; se puede
  cerrar la página. Tarda menos de un minuto en un servidor libre y unos minutos en uno ocupado; el job se corta a los
  10 minutos.
- [ ] Si se rotó `APP_KEY` desde la última preparación: reiniciar (ver §6).

Cómo corre: `PrepareDemo` o `ResetDemo` en la cola `demo` de la conexión `redis-long`, que atiende el supervisor
`supervisor-demo` de Horizon, uno a la vez. Nunca corren dos: un segundo clic responde «Ya hay una preparación corriendo».
Una corrida que se queda en «running» más de 30 minutos (un worker que murió) se da por abandonada y la siguiente toma su
lugar (`demo.run_abandoned`); si su job llega tarde, ya no hace nada. Un fallo queda en la sala («La última preparación
falló en …» con la clase del error) y en el log `demo.run_failed` (nivel error), solo con la clase, nunca el mensaje.

## 3. La sesión

1. **Entrar como dueño** o **como RH** desde la sala (impersonación; para volver, «Salir»). En la demo se puede abrir
   Compensación y Humi aunque se esté impersonando.
2. **Abrir sala (2 h)**. El QR de la puerta se muestra **una sola vez**: proyectarlo en ese momento. Abrir otra vez cierra
   la sesión anterior y su QR deja de servir: si se pierde el QR, cerrar y abrir la sala. «Cerrar sala» la cierra antes
   de las 2 h.
3. Cada persona del público escanea y recibe **un trabajador demo propio** (hay 40 reservados, repartidos en los 4
   centros) y entra a su portal personal. Una cookie le devuelve el mismo trabajador si recarga. Cada trabajador se
   entrega **una sola vez hasta el siguiente reinicio**: no hay «liberar». Sin trabajadores libres la puerta dice
   «La sala está llena»: reiniciar. La sala muestra cuántos quedan libres.
4. Qué hace el público desde su portal:
   - contestar el **pulso** abierto o la **evaluación NOM-035** abierta (el avance sube en vivo);
   - **confirmar la política** publicada (sus acuses están pendientes a propósito);
   - poner una **denuncia** en el canal de la demo (QR en la sala). El canal de la demo acepta 60 envíos por hora y 120
     al día por dirección (un salón comparte la Wi-Fi); el tope del canal, 200 al día, sigue (lo que pase de ahí se
     recibe y se retiene al día siguiente);
   - **marcar asistencia en vivo**: la demo no trae passkeys sembradas. Cada quien vincula su teléfono desde
     «Mi jornada» y marca frente a la pantalla del checador de Oficinas Querétaro. El enlace de esa pantalla se emite en
     la sala, se muestra una vez y deja el checador sin activar hasta que se abre en la tableta o el proyector. Una vez
     que la pantalla muestra su código no se emite otro: para otro enlace, reiniciar.
5. **Seguimiento de una denuncia:** las claves de las denuncias sembradas no se guardan; para enseñar el seguimiento,
   poner una en vivo y usar su clave.

## 4. Qué enseñar y qué evitar

- **Evitar «Por jefe» en los pulsos.** Lista grupos por jefe y cada uno enlaza a un detalle que responde 404 (bug
  conocido de Engagement, pendiente de su propio arreglo).
- **Ley Silla:** tres puntos quedan en «Falta» a propósito, para completarlos en vivo: R3 (minuta de la Comisión),
  R10 (reglamento interior) y R12 (análisis integrado).
- **Compensación:** el escenario de Monterrey se calcula con el impuesto sobre nómina de Querétaro (la calculadora lee el
  estado de la persona, no el del centro). No enseñar ese detalle como si fuera correcto.
- **Humi está vivo:** cada pregunta es una llamada real al modelo, cuesta y llega a PostHog como `$ai_generation`.
- **Acuses de la política: 77 %.** No es un error: las 40 personas de la puerta quedan pendientes.

## 5. Lo que nunca sale

- **Correo:** ninguno. Todas las personas de la demo usan `@demo.humetria.test` y un listener cancela todo mensaje cuyos
  destinatarios son todos de ese dominio (`demo.mail_blocked`, nivel debug: no llega a PostHog con
  `LOG_POSTHOG_LEVEL=info`). Cada preparación encola y cancela del orden de 1 455 avisos.
- La demo no cuenta en cobros, métricas ni conteos de la consola, y los resúmenes y recordatorios programados se la
  saltan.
- Toda página de la demo lleva la marca **DEMO**.

## 6. Notas de seguridad

- **Excepción a las tablas de solo inserción.** Una migración deja que 14 tablas de solo inserción (respuestas anónimas,
  tamizajes de Guía I, clima, marcas y convenios de asistencia, movimientos de vacaciones, uso, evidencia de difusión,
  evaluaciones de Ley Silla firmadas) **borren filas solo de la empresa con `is_demo`**. UPDATE y TRUNCATE siguen
  bloqueados para todas, la demo incluida. `audit_entries` no se toca: crece un poco con cada reinicio.
- **`APP_KEY`.** Sellan con ella el código del canal de denuncias de la demo (su QR), los enlaces del portal y de las
  encuestas. Al rotarla la sala muestra un QR nuevo que solo sirve tras reiniciar, y los textos cifrados viejos solo se
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
