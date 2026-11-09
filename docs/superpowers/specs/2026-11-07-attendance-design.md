# Registro de jornada (módulo `Attendance`): diseño

Fecha: 2026-11-07 · Estado: **aprobado** (2026-11-07) · Base legal y su interpretación:
[`docs/normas/jornada/criterios.md`](../../normas/jornada/criterios.md) (manda sobre este documento).

## Para qué y por qué ahora

Desde el **1 de enero de 2027** toda persona empleadora debe registrar de forma electrónica la jornada de cada
persona trabajadora (LFT art. 132-XXXIV; multa de 250 a 5,000 UMA). La STPS no impone una plataforma ni un aparato y
todavía no publica a quién exime. El registro «hará prueba plena si se acredita que fue acordado» entre las dos partes.

Humetría ya vende cumplimiento ante la STPS (NOM-035, NOM-037, Ley Silla). Esto es el siguiente: el **registro de
jornada defendible**. La competencia (Integratec) ofrece reloj checador, turnos y vacaciones como funciones de una
suite de RH. Nosotros no competimos en cantidad de funciones sino en que **el registro se sostenga ante una
inspección**: se puede demostrar que lo acordó la persona, que no se alteró y desde dónde se hizo.

## Metas

1. Que cualquier empresa cumpla el 132-XXXIV el 1 de enero de 2027 con lo que le vendemos.
2. Que la persona marque entrada, pausas y salida desde su teléfono (o su computadora, el personal de confianza) **sin
   correo ni contraseña** y de forma segura.
3. Que el registro sea de **solo inserción**, con correcciones trazables y con el **acuerdo de la persona** demostrable.
4. Que RH vea el cumplimiento de los topes del decreto (jornada, horas extra, 12 h al día, descanso) **antes** de rebasarlos.
5. Que un botón arme el reporte para la autoridad.

## No metas

- Biométricos propios (huella, rostro) ni lectores de hardware. Sí se usa el desbloqueo del teléfono (criterio de seguridad).
- Nómina, vacaciones, permisos, incapacidades (módulo `Leave`, después), ni organigrama más allá de `manager_id`.
- Turnos rotativos complejos y bolsa de horas. La primera fase tiene un horario semanal fijo por persona.
- Aplicación nativa. Es una app web instalable (PWA) y funciona en navegador.
- Marcar sin conexión. Toda marca la sella el servidor.

## Anti-metas (si pasa esto, falló aunque «funcione»)

- Que alguien pueda editar o borrar una marca ya hecha sin dejar rastro.
- Que se marque por otra persona sin que el sistema pueda notarlo razonablemente.
- Que la ubicación se capture fuera del momento de marcar, o la vea quien no debe.
- Que una regla de la STPS (excepciones, plazos) obligue a cambiar código.
- Que las coordenadas o las marcas aparezcan en logs o en la bitácora.

## Estructura

```
Organization  → ya existe. Se agrega manager_id (organigrama); expone contratos de plantilla.
Attendance    → NUEVO. Marcas, dispositivos, zonas, QR, política, cálculo, conformidad, reporte.
Leave         → DESPUÉS. Attendance le preguntará por un contrato (Absences). Leave no conoce a Attendance.
```

`Attendance` lee personas por el contrato de `Organization` (`Roster`), centros de trabajo por el de `Tenancy`
(`WorkCenters`) y quién trabaja a distancia por el de `Telework`. No importa modelos ajenos. Publica eventos
(`AttendanceMarked`, `DayClosed`, `WeekConfirmed`, `WeekDisputed`) con ids, nunca con coordenadas.

### Modelos (todos con `tenant_id` y `BelongsToCurrentTenant`)

- `AttendanceDevice`: dispositivo vinculado de una persona. Credencial WebAuthn (id público y llave pública), etiqueta,
  banderas de respaldo (`backup_eligible`, `backup_state`: si es una llave sincronizada), `enrolled_at`, `last_used_at`,
  `revoked_at`. Una persona puede tener varios; RH puede revocar.
- `AttendanceAgreement` / `AgreementAcceptance`: texto versionado del acuerdo y la aceptación de cada persona
  (versión, momento, dispositivo). Es la evidencia del criterio 1.3 y 5.1.
- `AttendanceZone`: zona de un centro de trabajo (nombre, centro y radio en metros). Un centro tiene una o varias
  («Edificio A», «Almacén»). Círculos, no polígonos. // ponytail: círculos bastan; polígonos si un cliente lo pide.
- `AttendanceMark`: **solo inserción**. Persona, tipo (`start`, `break_start`, `break_end`, `end`), momento del
  servidor, método, nivel de evidencia, `zone_id` (nulo si fuera de zona), latitud, longitud, precisión, distancia al
  centro de la zona, dispositivo, y para correcciones: `corrects_mark_id`, `corrected_by`, `reason`. Trigger de Postgres
  que rechaza UPDATE, DELETE y TRUNCATE (como `audit_entries`).
- `AttendancePolicy`: por empresa, qué métodos valen por grupo (`StaffGroup`) y modalidad; si exige QR, geocerca o ambos;
  tolerancias; plazo de conservación. **Los topes legales no van aquí: van en una tabla por año** (criterios 2.1 y 2.3).
- `WeeklyConfirmation`: por persona y semana: estado (`pending`, `confirmed`, `disputed`), momento, objeción y resolución.
- `AttendanceSchedule`: horario semanal acordado de la persona (criterio 2.6).

## Seguridad del enlace personal con dispositivo vinculado

Objetivo: que la persona marque **sin correo ni contraseña**, con un factor que **no se pueda reenviar** como un enlace.
Estándar: **WebAuthn / passkeys** (W3C, el mismo que ya usamos para la cuenta de RH): una llave de acceso de la persona,
que se desbloquea con verificación de usuario (huella, rostro o PIN del teléfono) en cada marca. La llave queda ligada a
la **cuenta de llaves de acceso de la persona** (iCloud, Google o el propio dispositivo), **no se puede demostrar que a un
solo dispositivo físico**: las llaves sincronizadas (lo normal en iPhone y Android, con contador siempre en 0) se
aceptan. Se guardan sus banderas de respaldo (BE/BS) para que RH las vea, y una llave no puede cambiar después si es
sincronizable. Un enlace por sí solo no es un factor seguro: si se reenvía, lo usa otro.

1. **Alta.** RH invita a la persona. Recibe un **enlace de un solo uso y de corta vigencia** (por el canal de correo ya
   existente). Lo abre **en su teléfono**, acepta el acuerdo (5.1) y **crea una llave de acceso** en el dispositivo.
   El enlace queda consumido; se guarda `AttendanceDevice`.
2. **Marcar.** La app web instalada pide una **aserción WebAuthn** con verificación de usuario. El servidor verifica
   firma, desafío (de un solo uso, expira en 60 s), origen, contador de firmas y que el dispositivo no esté revocado.
   La credencial identifica a la persona: no hay usuario ni contraseña.
3. **Más dispositivos y pérdida.** Un segundo dispositivo exige un enlace nuevo de RH. RH revoca un dispositivo al
   instante; todo queda en la bitácora.
4. **Límites del intento.** Límite de intentos por dispositivo y por IP; sin enumeración (respuestas iguales ante
   credenciales inexistentes); desafíos y enlaces de un solo uso.
5. **Personal de confianza.** Mismo mecanismo en su computadora (llave de acceso en el navegador o en el teléfono vía
   código). No tienen `User`.

**Límites honestos:** ningún método impide que alguien con el dispositivo desbloqueado de otra persona marque por ella.
Una llave sincronizada vive en todos los dispositivos de la cuenta de la persona, y sin contador no se detecta su copia:
la llave prueba la cuenta de la persona y su huella, rostro o PIN, no un teléfono concreto. El alta exige un enlace de un
solo uso que solo llega al correo de la persona (RH lo ve solo si la persona no tiene correo, y queda en la bitácora), y
la persona recibe un aviso por correo de cada dispositivo vinculado. El sistema lo hace *difícil y detectable*, no
imposible: QR con ventana de 30 s + geocerca (el nivel de evidencia de cada marca), marcas sospechosas señaladas (misma
ubicación y hora para varias personas, saltos imposibles), las banderas de respaldo de cada dispositivo a la vista de
RH, y el nivel de evidencia a la vista.

## QR rotativo y geocerca

- **QR.** Cada centro de trabajo tiene una **pantalla de QR** (página protegida, para una tableta fija) que muestra un
  código firmado con HMAC-SHA256 sobre (centro, ventana de 30 s). El servidor acepta la ventana actual y la anterior.
  Un código de una foto reenviada caduca en segundos. La pantalla del QR se autoriza con un token de dispositivo del
  centro que RH puede revocar.
- **Geocerca.** Al marcar, la app pide la ubicación (una sola vez, en ese momento) y el servidor calcula la distancia a
  cada zona del centro (Haversine, en PHP) y guarda las coordenadas, la precisión, la zona y la distancia. Una precisión
  peor que el umbral de la política baja el nivel de evidencia. Marcar fuera de toda zona es posible si la política lo
  permite (queda señalado); si no, se rechaza.
- **Niveles de evidencia** (criterio 5.3): `qr_and_geofence` (fuerte) > `geofence` o `qr` > `remote` /
  `trust_browser` > `correction` (débil).

## Pantallas (partir del rediseño en `docs/design-system/pantallas/`)

- Persona (móvil): **Marcar** (botón grande según el siguiente paso, estado del día, aviso de ubicación), **Mi semana**
  (marcas, horas, confirmar u objetar), alta del dispositivo y aceptación del acuerdo.
- RH: **Asistencia** (resumen por estado y semáforo de cumplimiento, detalle paginado por centro), **Correcciones**
  (cola con motivo), **Objeciones**, **Dispositivos**, **Centros y zonas** (mapa y pantalla del QR), **Política**,
  **Reporte para la autoridad**.
- Todo lo que sea una lista va paginado en el servidor. Cada página con `visitPage()` en Browser y con la prueba de que
  sus props no incluyen coordenadas de más.

## Reglas de privacidad y de bitácora

- Las coordenadas y la hora exacta son datos personales de una persona identificada (esto **no** es anónimo como las
  encuestas). Solo los ven RH autorizada y la propia persona.
- Los logs llevan eventos con ids (`attendance.marked`); `latitude` y `longitude` se agregan a `RedactSensitiveData`
  con su prueba. Ver el reporte o descargarlo, corregir una marca, revocar un dispositivo: a la bitácora (`RecordAudit`).

## Orden de trabajo (un PR, commits atómicos; pensado para poder recortar)

1. Tabla por año de topes + cálculo de jornada y horas extra (Unit, lógica pura).
2. Marcas de solo inserción + correcciones + política.
3. Alta del dispositivo (WebAuthn), acuerdo y aceptación.
4. Marcar con QR y geocerca con zonas.
5. Conformidad semanal.
6. Panel de RH (resumen, semáforo, listas paginadas) y reporte para la autoridad.

## Qué queda al constructor

Nombres de rutas, columnas y enums en inglés; estructura de acciones; el diseño de pantallas dentro del rediseño;
cómo probar WebAuthn en navegador (autenticador virtual de Playwright).

## Decisiones de diseño tomadas por mí (revisar al leer)

Salieron de la revisión del constructor; no las dijo el usuario.

- **Tipo de jornada** (diurna, nocturna, mixta): campo de `AttendanceSchedule`, no se deduce de las horas. Fija el tope
  diario de 8, 7 o 7.5 h.
- **Modalidad** (presencial o remota): campo por persona en `AttendanceSchedule`, lo fija RH. No se lee de `Telework`.
- **`manager_id` y organigrama: fuera de la primera fase.** Las correcciones las aprueba `owner` o `hr`.
- **Correcciones.** La persona **solicita** una corrección con motivo; `owner`/`hr` la aprueba y se inserta una marca
  nueva (`corrected_by` es el `User` de RH). RH también puede corregir directo. Los cálculos usan la marca vigente de
  cada hueco (la corrección sustituye a la original), y el detalle muestra ambas.
- **Semana y día.** Semana de lunes a domingo en `app.display_timezone`. Un job al terminar el día local cierra el día
  (`DayClosed`); un fin sin marcar se **señala**, no se cierra solo. **El silencio no es conformidad**: una semana sin
  confirmar sale como «sin confirmar» en el reporte. Una corrección posterior a la confirmación reabre la semana.
- **Coordenadas.** Cada zona lleva su propio centro (latitud y longitud) y radio. Se capturan con un formulario y un
  botón «usar mi ubicación actual»; sin mapa ni librería nueva ni origen nuevo en la CSP. El mapa queda para después.
- **Pantalla del QR.** Token de dispositivo propio del centro (hash en base, se muestra una sola vez, revocable),
  distinto del flujo WebAuthn de las personas. Vigencia efectiva de unos 60 s: aceptable.
- **Conservación.** Un número de meses por empresa, mínimo 12 después de terminada la relación. **No se construye
  borrado** ahora (el trigger de solo inserción lo impediría); se decide cuando haya un cliente con tanto historial.
- **Marcar fuera de toda zona: se bloquea por defecto** (decisión del usuario); la política de la empresa puede cambiarlo a permitir y señalar.

## Decisiones del usuario (2026-11-07)

1. **Dependencia nueva aprobada:** `web-auth/webauthn-lib` como dependencia directa, para WebAuthn con `Employee`.
2. **Criterios aprobados** tal como están (incluidos 2.8 y 7.1).
3. **Fuera de toda zona se bloquea por defecto**; la empresa puede permitir y señalar. Siempre queda la corrección.

## Cambios durante la ejecución

Lo que cambió respecto de lo aprobado al construirlo (detalle y estado en el historial de
`docs/normas/jornada/criterios.md`, v0.3). Los marcados ⚠ esperan la decisión del usuario.

- **Llaves sincronizadas aceptadas** (iOS y Android respaldan la llave en la cuenta de la persona): «Límites honestos»
  ya no promete que la llave no se pueda copiar; se guardan sus banderas de respaldo (BE/BS) y RH las ve. ⚠
- **RH no recibe el enlace de alta** de quien tiene correo; solo el de quien no lo tiene, y la bitácora lo dice, con la
  dirección de envío enmascarada. Queda abierto que RH cambie el correo de alguien antes de enviarle el enlace: se ve en
  la bitácora, no se impide (cerrarlo exige un aviso al correo anterior, en Organization). ⚠
- **Aviso al vincular un dispositivo**, en cola (no lleva secretos); el correo con el enlace sale al momento y nunca
  entra a la cola. El envío a todo un centro encola un trabajo por persona (único por persona mientras espera), que crea
  el enlace en el trabajador y se salta a quien ya vinculó un teléfono; si el correo falla, se anota
  `attendance.enrollment_email_failed` con ids.
- **Conformidad semanal ligada a una huella** de las marcas leídas (`week_changed` si cambiaron) y bloqueada mientras la
  última jornada siga abierta (`day_open`).
- **Marcar recuerda a la persona 10 min** en ese navegador, solo para ofrecerle su siguiente paso, con «¿No es usted?»
  para olvidarla al instante. La página pide la ubicación en cada marca, también a quien trabaja remoto o es de
  confianza (el servidor la descarta para ellos), para no pedir una segunda huella. ⚠
- **Cierre del día** con `attendance:close-days` (artisan, programado), cierra el día anterior al de ayer; `DayClosed`
  puede repetirse si se vuelve a correr: quien lo escuche debe ser idempotente.
- **Sin horario**: 8 h cada día y sin día de descanso hasta que RH fije uno; el resumen lo señala con «Sin horario». ⚠
- **Reporte**: hasta 93 días en pantalla, hasta 366 en CSV; entre 94 y 366 la página explica y ofrece solo el CSV. Las
  horas se reparten con el horario vigente al generar el reporte (no hay historial de horarios).
- **Conservación**: de 12 a 120 meses, a elección de la empresa; el tope de 120 lo fijó el constructor. ⚠
- **Configuración** en `config/attendance.php` (ventana del QR y ventanas pasadas aceptadas, vigencia del enlace de alta,
  del desafío WebAuthn y de la memoria de Marcar, días del reporte en pantalla y en CSV, tamaño de bloque del reporte).
  Los topes legales siguen en el código (`LegalLimits`): son ley, no configuración. Los textos de la interfaz que citan
  «30 s» y «48 h» no leen esa configuración: si se cambia, hay que cambiarlos.
