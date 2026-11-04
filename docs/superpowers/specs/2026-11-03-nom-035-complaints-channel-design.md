# NOM-035: canal de quejas y denuncias confidencial (8.1 b, 6.4, 6.5)

Fecha: 2026-11-03. Estado: propuesta (pendiente de aprobación). Es el trabajo 4 de
[`docs/normas/nom-035/gaps-pendientes.md`](../../normas/nom-035/gaps-pendientes.md). Solo diseño: no hay código, migración
ni prueba todavía. El plan de implementación se escribe al aprobarse este documento y responderse las «Preguntas abiertas».

Anexos obligatorios:

- [`docs/normas/nom-035/criterios.md`](../../normas/nom-035/criterios.md) (versión 1.2): cómo y por qué se aplica la norma.
  Manda sobre este documento. Aquí se cita como C-nn. Los criterios nuevos de este trabajo se escriben ahí al
  implementar (ver «Criterios nuevos»), y se quita «mecanismo de quejas (8.1 b)» de «Lo que la plataforma no cubre».
- [`docs/normas/nom-035/fuentes/`](../../normas/nom-035/fuentes/README.md): el DOF del 23/10/2018 y las preguntas
  frecuentes de la STPS. Los numerales de este diseño se verificaron contra `dof-2018-10-23-nom-035.txt` (tabla de abajo).
- `CLAUDE.md`, secciones «Privacidad (invariante)», «Logging y auditoría», «Seguridad», «Multi-tenancy» y «Listas».

Este documento dice **qué se construye y por qué**. Es el diseño más delicado en privacidad de la plataforma: una queja
puede ser contra RH o contra el dueño, y la persona que la presenta puede perder el empleo si se le identifica.

## Qué dice la norma (verificado contra el DOF)

| Numeral | Qué pide | Cómo lo usamos |
|---|---|---|
| 8.1 b | Los centros de trabajo deben **disponer de mecanismos seguros y confidenciales** para recibir quejas por prácticas opuestas al entorno organizacional favorable y denuncias de actos de violencia laboral | Es la obligación del patrón. Todo el diseño |
| 6.4 | La persona trabajadora debe informar de esas prácticas y denunciar la violencia laboral **usando los mecanismos que establezca el patrón y/o a través de la comisión de seguridad e higiene** (NOM-019) | La Comisión es un receptor legítimo según la propia norma: puede ser encargada del canal |
| 6.5 | Aviso **por escrito** de haber presenciado o sufrido un acontecimiento traumático severo, al patrón, a los servicios preventivos o a la comisión. Contenido mínimo: fecha de elaboración, **nombre de quien lo elabora**, nombres de los involucrados (si los hay), fecha de ocurrencia y descripción | Tercer tipo del mismo formulario. Como exige el nombre, **no se acepta anónimo** (C-nn) |
| 5.6 | Exámenes médicos y evaluaciones psicológicas a quienes estén expuestos a violencia laboral cuando existan signos o síntomas, el análisis lo sugiera «y/o existan quejas de violencia laboral mediante los mecanismos del 8.1 b» | La existencia de una denuncia es un disparador. El módulo no canaliza a nadie; eso es el registro de canalización (gap 1) |
| 5.7 d | Difundir a las personas los mecanismos para presentar quejas y denunciar | Lo hace el diseño de política y portal (gap 3) con el contrato público de este módulo |
| 8.2 g 2 y 3 | Procedimientos de actuación y seguimiento para la violencia laboral, **capacitar al responsable**, e informar cómo denunciar | El canal lleva su procedimiento por escrito (editable, con plazos). La capacitación del responsable es del programa (gap 2) |
| 4.12 | Violencia laboral: actos de hostigamiento, acoso o malos tratos contra la persona trabajadora que pueden dañar su integridad o salud | De aquí salen las tres categorías de violencia |
| 4.6 y 7.3 | Entorno organizacional favorable: pertenencia, formación, responsabilidades claras, participación y comunicación, cargas y jornadas regulares, evaluación y reconocimiento del desempeño | De aquí salen las categorías de «prácticas opuestas» (clasificación nuestra) |
| 10.2 (fila 5.4, 8.1 y 8.2) | La STPS verifica por **documental o entrevista** que el patrón «dispone de mecanismos seguros y confidenciales» | La evidencia que mostramos es la configuración y la operación del canal, no el contenido de los casos |
| 10.4 | Evidencias y registros «a que alude esta Norma» se conservan al menos un año desde su elaboración | **No fija** la retención de las quejas (no son un registro nombrado en 5.8). La retención es criterio de Humetría |
| Guía IV (ejemplo, no obligatoria) | La política de ejemplo habla de un «procedimiento de atención justo» sin represalias, que evita reclamaciones abusivas y «garantiza la confidencialidad de los casos», y de salvaguardar la información recolectada | Inspiración de principios; se rotula como ejemplo, no como requisito |
| STPS-1, pregunta 10 | La denuncia ante la Dirección General de Inspección Federal del Trabajo puede ser anónima si da razón social, ubicación del centro y expone las circunstancias | Es la alternativa externa que el formulario muestra cuando no hay encargado independiente (ver «Receptor») |

**Lo que la norma calla y por tanto es criterio de Humetría** (se rotula así en pantalla y en `criterios.md`): que el
canal sea en línea y por QR, que haya opción anónima, las categorías, los plazos de acuse y resolución, el seguimiento
con folio y clave, la retención, el enrutamiento por conflicto de interés y el mínimo por grupo en las estadísticas. La
norma **no** exige anonimato: exige mecanismos «seguros y confidenciales». Lo anónimo es nuestra decisión para que el
canal sirva de verdad.

## Intención

Que una empresa pueda mostrar a la STPS que **dispone de un mecanismo seguro y confidencial** y que sus trabajadores
pueden usarlo sin miedo: un enlace (y un cartel con QR) por empresa, un formulario que se contesta en el celular en
cinco minutos, una opción anónima real con forma de dar seguimiento, y un expediente por caso al que solo entran las
personas encargadas.

Principios:

- **El modelo de amenaza es RH y la aplicación**, no el operador con acceso a la base (CLAUDE.md). Aquí «RH» incluye al
  dueño: una queja puede ser contra cualquiera de los dos.
- **Menos datos es más privacidad.** Lo que no se guarda no se filtra: ni IP, ni navegador, ni cookies, ni hora exacta.
- **Honestidad.** Se le dice a la persona qué protegemos y qué no. Prometer «anonimato total» sin serlo es el peor
  error posible de este módulo.
- **Reutilizar.** Comisión por contrato (`Telework\Contracts\Committee`), personas con acceso por `Tenancy\Contracts\Members`,
  centros por `Tenancy\Contracts\WorkCenters`, roles por Spatie, bitácora por `RecordAudit`.
- **Módulo propio `Complaints`**, sin meter nada en `Responses` (que es de respuestas de guías) ni en el kernel.

## Objetivos

1. El owner o RH **activan el canal** de la empresa y obtienen su enlace y un **cartel imprimible con QR**.
2. La persona abre el enlace **sin cuenta, sin cookies y sin iniciar sesión**, elige el tipo (queja por práctica opuesta,
   denuncia de violencia laboral o aviso de acontecimiento traumático severo), cuenta qué pasó, opcionalmente dónde,
   cuándo y contra quién, y decide si va **anónima** o **confidencial con su nombre**.
3. Al enviar recibe una **referencia** y una **clave de seguimiento** que se muestran una sola vez.
4. Con referencia y clave puede volver, **leer las respuestas** de la persona encargada, **contestar** y **retirar** su caso.
5. Cada queja llega solo a **personas encargadas designadas**, enrutadas por conflicto de interés (contra RH, contra la
   dirección) y por centro. Nadie más ve su contenido, ni el dueño por serlo.
6. La persona encargada trabaja el caso: acuse, revisión, notas internas, mensajes a la persona, excusarse, compartirlo con
   otra persona encargada y cerrarlo con una resolución.
7. Owner y RH ven el **estado del canal** (activo, cobertura de conflicto, plazos vencidos como conteos) y un **resumen
   estadístico con mínimo de 5**, sin contenido ni desgloses que delaten.
8. Una **página de evidencia** imprimible para la inspección (10.2: documental o entrevista) y un **contrato público** para
   que el portal y la política (gap 3) difundan el mecanismo (5.7 d).
9. **Retención acotada**: el contenido se purga a los N meses del cierre; queda un esqueleto sin datos personales.

## No objetivos

- Telefonía, WhatsApp, SMS o buzón de voz: solo web y correo (como las demás herramientas).
- **Adjuntos** (fotos, audios, capturas) en esta entrega: llevan metadatos (EXIF, autor) que identifican; se discute en
  «Alternativas».
- Investigar, sancionar o dictaminar: la plataforma registra el trámite; **no concluye si hubo violencia laboral**. La
  resolución es la clasificación de la persona encargada, rotulada como tal.
- Integrar con el registro de canalización (gap 1, 5.5/5.8 c) o con el programa de intervención (gap 2). Se deja el
  camino por eventos públicos futuros; hoy la persona encargada lo anota a mano.
- Canal telefónico externo («línea de ética» tercerizada), firma electrónica avanzada, mediación, estadísticas por
  persona señalada, asesoría legal.
- Nombrar o validar la identidad de quien reporta contra el padrón de empleados: la persona **no se autentica**.
- Protección contra represalias: la norma no la regula (solo la Guía IV la menciona como ejemplo). El producto no
  promete lo que no puede hacer cumplir.

## Anti-objetivos (fracasaría aunque funcione)

- **Que alguien con rol de RH o dueño vea a quien reportó** sin que esa persona lo haya decidido, o que vea una queja
  contra sí mismo.
- **Guardar IP, user-agent, cookies de sesión, `Referer` o la hora exacta** de un envío anónimo, en base, en logs, en
  cola o en caché persistente.
- **Correlacionar** una queja con una persona del padrón, con una invitación, con un token del portal o con una respuesta
  de Guía II/III. Ninguna tabla del módulo tiene llave hacia empleado, invitación, token o respuesta (CLAUDE.md).
- **Prometer «anonimato total»**. Se promete lo que se cumple y se dicen los límites.
- **Contenido de una queja en logs, bitácora, correos, colas, resultados de búsqueda (⌘K), Humi o props de páginas de RH
  que no sean el expediente del caso.**
- **Un canal «que existe» pero que nadie independiente atiende** (queja contra la dirección que llega al dueño).
- **Estadísticas que delaten**: «1 queja contra el dueño en el centro Monterrey».

## Modelo de amenaza

| Quién | Qué quiere | Qué lo frena |
|---|---|---|
| Persona señalada (jefe, compañero, RH, dueño) | Saber quién la denunció | Enrutamiento por conflicto (nunca ve su caso); sin datos del denunciante que guardar; texto cifrado; 404 y no 403 |
| RH / dueño no encargados | Leer casos «por curiosidad» o por presión | Acceso solo por asignación explícita al caso; lo demás es 404; solo ven conteos con mínimo 5 |
| Encargado curioso o coludido | Reidentificar a la persona anónima | No hay con qué: sin IP, UA, cookies ni hora; contacto anónimo es de solo escritura; todo acceso queda en la bitácora |
| Dueño que se designa encargado después de la queja | Leer casos viejos | Un encargado nuevo no ve casos previos: solo por asignación (ver «Receptor») |
| Compañero que ve la pantalla o el historial del celular | Ver el caso | Sin sesión que robar; la clave no vive en el navegador; `no-store`; aviso en la pantalla de la clave |
| Bot o alguien que inunda el canal | Saturar a las personas encargadas | Límites por dirección (con hash) y por canal, honeypot, boleto firmado, sin CAPTCHA de terceros |
| Operador con acceso a la base | Fuera del modelo | Aun así: texto cifrado, sin IP/UA/hora, caché de límites solo en Redis con TTL |
| La red de la empresa (proxy, Wi-Fi), el proveedor de internet | Ver que alguien visitó el sitio | **Fuera de nuestro alcance; se le dice a la persona** y se recomienda datos móviles |

## Alternativas y recomendación

### 1. Quién recibe las quejas

| Opción | Pros | Contras |
|---|---|---|
| A. Owner y RH ven todo | Simple; ya existen los roles; funciona el día 1 | Falla justo cuando importa: queja contra RH o contra el dueño. En empresas chicas el dueño **es** RH. No cumple «seguro y confidencial» frente a la persona señalada |
| B. Rol nuevo dedicado, owner/RH solo ven conteos | Separación limpia | Una empresa de 12 personas no tiene a quién designar; bloquea la activación; el dueño igual puede invitarse al rol |
| C. Solo la Comisión de seguridad e higiene | La norma la nombra (6.4); es mixta (patrón y trabajadores) | Sólo existe por centro y no siempre está constituida; hereda sus propios conflictos |
| D. Tercero externo (línea de ética) | Máxima independencia | Contrato, costo, dependencia de proveedor; fuera de alcance del MVP |
| **E. Personas encargadas designadas + enrutamiento por conflicto + asignación por caso (recomendada)** | Funciona el día 1 (el owner queda designado por omisión) y mejora conforme la empresa designa a más personas, incluso externas; permite la Comisión; evita acceso retroactivo | Más piezas (designación, asignación, matriz de cobertura) |

**Recomendación: E.** Detalle:

- **Encargadas del canal** (`complaint_handlers`): usuarios de la empresa con el permiso `handle-complaints` que la empresa
  designa, por empresa o por centro. Pueden ser owner, hr, miembros de la Comisión (`committee`) o un **rol nuevo**
  `complaints` («Atención de quejas») pensado para alguien externo: un abogado, una persona de confianza, un representante
  sindical. Ese rol no ve nada más de la plataforma (como `committee` hoy).
- **Por omisión**, al activar el canal queda designada la persona que lo activa (owner o hr), para que sirva el día 1.
- **«Contra quién»** es una pregunta del formulario con seis opciones de **rol, no de nombre**: jefe inmediato,
  compañero, recursos humanos, dirección o dueño, cliente o tercero, «no sé / prefiero no decirlo».
- **Enrutamiento por conflicto** al recibir:
  - contra **recursos humanos** → no se asigna a encargadas con rol `hr`;
  - contra **dirección o dueño** → no se asigna a encargadas con rol `owner`;
  - el resto → todas las encargadas cuyo alcance incluye el centro elegido (o la empresa).
- **Asignación explícita por caso** (`complaint_assignments`): ver un caso exige una asignación vigente. Una encargada
  designada **después** no ve los casos anteriores salvo que una asignada la agregue. Así, un dueño que se designa tras
  una queja contra él no la lee. Un caso sin ninguna asignada aparece como conteo («1 caso sin encargada») a quien
  administra el canal, que puede asignarlo **solo a encargadas no excluidas por el conflicto** y sin asignarse a sí misma
  si el conflicto la excluye.
- **Excusarse:** cualquier asignada puede declararse en conflicto; sale del caso y queda anotado.
- **Cobertura:** el panel del canal muestra, por cada opción de «contra quién», **cuántas encargadas lo recibirían**. Si
  una opción queda en cero (típico: dirección o dueño en una empresa donde el dueño es la única persona designada), se
  muestra **Falta** y el formulario **no acepta esa opción**: explica que la empresa aún no tiene a nadie independiente
  para eso y ofrece la alternativa externa (la Dirección General de Inspección Federal del Trabajo; texto y datos en
  `config/complaints.php` con fuente STPS-1 pregunta 10 y fecha). Alternativa descartada: aceptarla y entregarla al dueño;
  la persona creería que está protegida y no lo está.
- **Límite honesto:** la plataforma no puede proteger de quien controla toda la empresa. Un dueño puede quitar a las
  encargadas o inventarse una. Mitigaciones reales: la lista de encargadas y sus cambios quedan en la bitácora, cada
  cambio avisa por correo a las demás encargadas, y el canal ofrece designar a una persona **externa**. Esto va en el texto
  de ayuda del panel y en las preguntas abiertas.

### 2. Anonimato y seguimiento

| Opción | Pros | Contras |
|---|---|---|
| A. Anónima sin retorno (disparar y olvidar) | Máxima privacidad; trivial | La persona encargada no puede pedir más datos; la persona no sabe si se atendió: el canal pierde utilidad y confianza |
| B. Seguimiento por correo (relay) | Cómodo | El correo identifica casi siempre (nombre.apellido@empresa); lo ve el proveedor de correo; rompe la promesa |
| C. Cuenta anónima (passkey o token guardado en el navegador) | Cómodo | Dispositivo compartido o limpiado pierde el acceso; mete estado y cookies |
| **D. Referencia + clave de seguimiento que solo conoce la persona (recomendada)** | Sin cuenta, sin cookie, sin correo; solo quien tiene la clave lee y contesta | Si pierde la clave no hay recuperación (por diseño) |
| E. Enlace de seguimiento con la clave en el fragmento `#…` | El fragmento no viaja al servidor | Quien tenga el marcador o el historial lee el caso; se descarta |

**Recomendación: D, con un complemento opcional** y tres modos de identidad:

- **Anónima (por omisión).** Sin nombre ni contacto. La referencia y la clave son el único hilo.
- **Confidencial con identidad.** La persona escribe su nombre (y, si quiere, un contacto) y solo lo ven las personas
  asignadas. No existe un modo «identificada para todos»: la confidencialidad es la propiedad del canal, no una opción.
- **Aviso por correo de solo escritura (opcional, también en la anónima).** Si deja un correo, **nadie lo ve nunca** (ni la
  persona encargada); el sistema solo lo usa para decirle «hay novedades en su caso» con un enlace al seguimiento, sin
  referencia ni contenido, y lo borra 30 días después de cerrar el caso. En modo confidencial el correo es el contacto
  visible. El texto lo dice con esas palabras. Es el único dato personal que una queja anónima puede tener, está cifrado,
  nunca va a logs (`email` ya se redacta) y es opcional. **Es separable:** va en el último commit del núcleo y se puede
  descartar sin tocar el resto (pregunta 6).

**¿Anonimato total?** No, y no se promete. Es anonimato **técnico en la aplicación**: no guardamos IP, navegador, cookies
ni hora exacta, y el contacto anónimo es de solo escritura. Quedan, y se dicen en pantalla, tres límites: (1) **el
contenido** puede identificar (detalles que solo una persona sabe, estilo de redacción), (2) **el centro y la persona
señalada** acotan el grupo, y (3) **la red** (Wi-Fi o proxy de la empresa, proveedor de internet) puede ver que se visitó
el sitio; se recomienda datos móviles.

**¿Se descarta la correlación por hora, IP y user-agent?** Sí, las tres:

- **Hora.** `complaints` no tiene `created_at` ni `updated_at`. Guarda `received_on` (solo fecha). Los mensajes de la
  persona llevan fecha, no hora; el orden del hilo se guarda con un `position` secuencial. Es la misma regla de
  `AnonymousResponse` (C-22) aplicada a las quejas. Costo: los plazos se miden en días (suficiente: acuse y resolución son de días).
- **IP.** Nunca llega a la base ni a un log. Los límites de frecuencia usan una **clave con HMAC de la dirección** y viven
  solo en Redis con TTL de una hora a un día (en producción el limitador usa la caché `redis`, no la tabla `cache`). Hoy
  los limitadores existentes usan `$request->ip()` crudo como llave; **el canal no copia ese patrón**.
- **User-agent, `Referer`, idioma.** Nunca se leen en el código del módulo. El `Referer` puede traer el token del portal
  (la ruta `portal/{token}` es la credencial de la persona): el portal debe enlazar con `rel="noreferrer"` (ver
  «Integración con el portal»), y el canal responde con `Referrer-Policy: no-referrer`.
- **Cookies y sesión.** Las rutas públicas **no inician sesión** (`SESSION_DRIVER=database` guarda `ip_address` y
  `user_agent` por sesión): ni `StartSession`, ni cookies, ni CSRF de sesión. El envío es un POST JSON sin redirecciones
  ni flash; la protección CSRF es un **boleto firmado** (ver «Anti-spam»).

### 3. Retención

| Opción | Pros | Contras |
|---|---|---|
| A. Conservar siempre | Evidencia máxima | Pasivo de privacidad permanente; datos sensibles sin fecha de caducidad |
| B. Plazo fijo que decide Humetría | Simple | Rígido: una empresa en litigio lo necesita más; otra, menos |
| **C. Plazo configurable con piso, techo y retención legal manual (recomendada)** | La empresa decide dentro de un rango sensato y puede congelar un caso | Más configuración |

**Recomendación: C.** `retention_months` por canal, por omisión **24**, mínimo **12**, máximo **60**, contados desde el
cierre. El piso de 12 meses es **criterio de Humetría por analogía con 10.4**, no una obligación de la norma sobre quejas;
el plazo que conviene lo valida un abogado (ver «Pendiente»). Una persona encargada puede marcar un caso con **retención
legal** (`on_hold`) y no se purga hasta que se levante; queda en la bitácora.

Al vencer, el trabajo diario `complaints:purge` **borra el contenido** (descripción, texto de la persona señalada,
nombres, contacto, entradas del hilo, referencia, hash de clave y asignaciones) y deja un **esqueleto**: tipo, categoría,
resolución, mes de recepción (primer día del mes) y fecha de cierre. Sirve para el conteo de «casos atendidos» como
evidencia de que el canal opera, sin datos personales. Casos que nunca se cierran no se purgan: se recuerdan como
«estancados» (90 días sin movimiento) en el aviso a las encargadas. El correo de aviso se borra 30 días después del cierre.
El borrado va a un log `complaints.purged` con conteos, sin ids.

### 4. Adjuntos

Se descartan en esta entrega: fotos y audios llevan EXIF, autor y huellas de dispositivo, y quitar metadatos de forma
fiable es un proyecto en sí. La persona describe por escrito. Si se pide más adelante: disco privado como
`seating_files`, reescritura de la imagen para borrar metadatos y límite de tamaño. Va como diseño propio.

### 5. Anti-spam sin guardar identidad

Descartados: CAPTCHA de terceros (el proveedor recibe IP y huella del navegador de quien denuncia; además exige abrir la
CSP) y cuentas. Se usa, en este orden:

1. **Límites de frecuencia** con clave HMAC de la dirección: por dirección, 5 envíos por hora y 30 consultas de
   seguimiento por hora (una oficina con una sola IP pública es normal: el límite no debe bloquear a una sala entera, por eso
   es por hora y configurable); por canal, un tope diario (por omisión 200) que al agotarse responde «intente más tarde»
   y avisa a quien administra el canal (conteo, sin contenido).
2. **Honeypot:** un campo oculto que un humano no llena. Si viene lleno se responde éxito falso (el bot no aprende) y no se guarda.
3. **Boleto firmado:** al abrir el formulario el servidor entrega un `ticket` (HMAC con la llave de la app, código del
   canal y minuto de emisión; **sin estado ni identidad**). El envío exige que tenga entre 3 segundos y 2 horas. Es también
   el CSRF de estas rutas. No es de un solo uso: lo que acota el reuso es el límite de frecuencia.
4. **Longitudes:** descripción de 20 a 5000 caracteres; sin enlaces automáticos.
5. **Descarte** por la persona encargada («es spam») con motivo; cierra el caso, no bloquea a nadie.

Prueba de trabajo (hashcash) en el navegador queda como palanca futura si hay inundaciones reales.

## Módulo `Complaints`

`app/Modules/Complaints/` sigue la estructura de CLAUDE.md. **API pública:**

- `Contracts/ComplaintChannels::current(): ChannelData` — lo que otros módulos necesitan para difundir el mecanismo.
- `Data/ChannelData` (readonly): `available` (bool), `url` (URL pública absoluta o null), `publishedOn` (fecha o null), `types`
  (lista de los tipos que acepta), `anonymousAllowed` (siempre true), `acknowledgeWithinDays`, `resolveWithinDays` y
  `procedure` (texto público). **Nada de casos, conteos, encargadas ni nombres**: es publicable.
- `Events/ComplaintChannelPublished` (tenant_id, published_on), `ComplaintChannelPaused` (tenant_id) y
  `ComplaintChannelCodeRotated` (tenant_id). **No hay evento de «queja recibida»**: nadie fuera del módulo debe reaccionar a
  una queja, y un evento con la hora de un envío anónimo es un hilo de correlación.

`Complaints` lee de otros módulos solo por contratos: `Tenancy\Contracts\Companies` (nombre comercial en la página
pública, estado de la empresa), `WorkCenters` (centros que se ofrecen), `Members` (quién es miembro y con qué rol; si
hace falta saber si es `owner` o `hr` sin importar `TenantRole`, se agrega al contrato del módulo dueño) y
`Telework\Contracts\Committee` (quién sienta en la Comisión de un centro). Nada se importa de internos ajenos. Un dato que
falte se agrega al contrato del dueño; no hay joins entre tablas de módulos distintos.

`Humi` (Assistant) **no** tiene ninguna herramienta que lea quejas, y las quejas no se indexan en la búsqueda global ⌘K:
una prueba busca una cadena de una queja y no la encuentra en ninguna.

## Modelo de datos

Todas las tablas de negocio llevan `tenant_id`, aplican `BelongsToCurrentTenant` y traen su prueba de aislamiento (la
empresa A no lee ni modifica lo de B, por consulta ni por URL con 404). Los nombres exactos los fija el constructor; la
estructura es:

- **`complaint_channels`**: una fila por empresa. `access_code` (16 caracteres Crockford base32, unique, aleatorio,
  rotable: es lo que va en el QR), `enabled_at` y `paused_at` (timestampTz), `published_on` (date), `procedure` (texto
  público, con modelo editable), `acknowledge_within_days` (check 1–15), `resolve_within_days` (check 7–180),
  `retention_months` (check 12–60), `access_code_rotated_on`. El canal se resuelve **por código sin tenant** (como el
  portal): `OpenChannel` lee sin el scope global, fija el tenant en `Context` y entrega 404 sin decir por qué si el
  código no existe, el canal está pausado o la empresa está bloqueada.
- **`complaint_handlers`** (encargadas): `user_id`, `work_center_id` nullable (alcance; null = toda la empresa),
  `designated_by`, `designated_at`, `ended_at` nullable. **Se termina, no se borra.** Índice único parcial por
  (tenant, usuario, alcance) donde `ended_at is null`.
- **`complaints`** (el caso; **sin `created_at` ni `updated_at`**, `#[WithoutTimestamps]`):
  - `id` UUID v4 (`HasVersion4Uuids`; nunca ordenable por tiempo), `tenant_id`, `channel_id`.
  - `reference` (8 caracteres Crockford, único por empresa, **visible** a la persona y a las encargadas) y
    `access_key_hash` (SHA-256 de una clave aleatoria de 20 caracteres, ~100 bits; único). La clave solo existe en la
    respuesta del envío.
  - `type` (`unfavorable_practice|workplace_violence|traumatic_event_notice`), `category` nullable (ver «Categorías»),
    `respondent_kind` (`direct_supervisor|coworker|hr|management|third_party|unknown`), `work_center_id` nullable.
  - `identity_mode` (`anonymous|confidential`), `status` (`new|in_review|closed`), `resolution` nullable
    (`measures_taken|no_action_needed|referred_externally|insufficient_information|withdrawn|discarded_spam`),
    `on_hold` bool.
  - **Cifrados** (cast `encrypted`, columnas `text`): `description`, `respondent_text`, `involved_text` (6.5),
    `reporter_name`, `reporter_contact` y `reporter_email`. Los metadatos que se filtran (`type`, `category`, `status`,
    `work_center_id`, `received_on`) van en claro; **no hay búsqueda por texto libre** a propósito.
  - Fechas: `received_on` (date, en `display_timezone`), `occurred_on` (date, 6.5 «fecha de ocurrencia»), `closed_on` (date),
    `acknowledged_at` y `closed_at` (timestampTz; son acciones de la **encargada**, no de la persona) y `purged_on`.
  - `check`s: categoría coherente con el tipo; `(status = 'closed') = (resolution is not null)`;
    `identity_mode = 'confidential' or reporter_name is null`; `type <> 'traumatic_event_notice' or reporter_name is not null`
    (el escrito 6.5 lleva el nombre de quien lo elabora); `purged_on is null or description is null`.
  - Esqueleto tras la purga: `type`, `category`, `resolution`, mes de recepción en `received_on` y `closed_on`.
- **`complaint_assignments`**: caso, usuario, `assigned_by` nullable (null = sistema al recibir), `assigned_at`, `ended_at`
  nullable, `end_reason` (`recused|handler_ended|reassigned|member_removed`), `notified_position` (smallint, hasta qué
  entrada ya se avisó). Único parcial por (caso, usuario) con `ended_at is null`.
- **`complaint_entries`** (hilo, notas y bitácora del caso): caso, `position` (smallint secuencial por caso, único),
  `kind` (`reporter_message|handler_message|internal_note|status_change|assignment_change|recusal`), `author_id` nullable
  (usuario; null cuando es la persona), `body` cifrado nullable, `meta` jsonb nullable (de/a estatus, usuarios; **nunca
  texto**), `occurred_at` (timestampTz, solo las de la encargada) y `occurred_on` (date, solo las de la persona), con
  `check ((kind = 'reporter_message') = (occurred_at is null and occurred_on is not null))`. La persona ve solo
  `reporter_message` y `handler_message`, y de estas últimas no ve el autor («Persona encargada»).
- **Sin llave** hacia empleado, invitación, token del portal, campaña ni respuesta. Una prueba recorre el esquema y falla
  si alguna columna de estas tablas apunta a `employees`, `participants`, `portal_links` o `anonymous_responses`.

### Categorías (clasificación de Humetría, no texto del DOF)

- `workplace_violence` (4.12): `hostigamiento`, `acoso`, `malos_tratos` (`harassment`, `bullying`, `mistreatment`).
- `unfavorable_practice` (4.6, 7.3, 8.1 c y 8.2 a 2): `workload_or_hours` (cargas o jornadas), `unclear_responsibilities`,
  `lack_of_training`, `lack_of_recognition` (evaluación y reconocimiento del desempeño), `communication_or_participation`,
  `discrimination` (8.2 a 2), `other`.
- `traumatic_event_notice` (6.5): sin categoría; lleva `occurred_on` e `involved_text`.

La interfaz rotula la lista como «clasificación de Humetría» y la pregunta de la persona no la obliga a escoger: «No estoy
segura/o» es válida. Otros marcos legales (por ejemplo protocolos de acoso sexual) pueden aplicar a lo que se reporte;
**el texto de ayuda lo menciona sin citar artículos** hasta que lo valide un abogado.

## Roles y permisos (Spatie, como los actuales)

Permisos nuevos, por migración como `view-seating`:

| Permiso | owner | hr | viewer | committee | complaints (rol nuevo) |
|---|---|---|---|---|---|
| `manage-complaint-channel`: activar, pausar, rotar código, plazos, procedimiento, retención, designar y terminar encargadas, cartel, evidencia, resumen | ✓ | ✓ | | | |
| `handle-complaints`: **poder** ser encargada y trabajar casos asignados | ✓ | ✓ | | ✓ | ✓ |

- **Tener el permiso no da acceso a ningún caso.** El acceso lo da estar **designada** y **asignada** al caso. Las rutas de
  un caso resuelven el modelo con una consulta limitada a las asignaciones vigentes del usuario: sin asignación, **404**
  (no 403, para no delatar que existe).
- `viewer` no ve nada del canal, ni conteos. Quien administra el canal sin ser encargada ve estado y resumen con mínimo.
- **Rol nuevo `complaints`** («Atención de quejas»): se agrega a `TenantRole`, a las invitaciones (`invitedAs`) y a la
  restricción de rutas que hoy limita `committee` a `tenant.telework.*` y `tenant.seating.*`
  (`LimitCommitteeToTelework`); esa restricción se generaliza a «rol restringido a sus prefijos». El constructor decide el
  nombre del middleware; no se duplica la lógica.
- **Suspender o quitar** a un miembro de la empresa termina sus designaciones y asignaciones (`member_removed`) y deja el
  caso, si queda sin nadie, en el conteo de «sin encargada».
- **Impersonación del superadmin:** las rutas de casos van en `ForbidWhileImpersonating` y una prueba lo exige. Es hardening
  barato aunque el operador esté fuera del modelo.
- Cada designación o terminación de encargada: bitácora y correo a las demás encargadas.

## Flujos

### Activación (owner o hr)

`Cumplimiento → Quejas y denuncias → Canal`. Una lista de verificación: procedimiento escrito (con un modelo editable), al
menos una encargada, cobertura de cada opción de «contra quién», plazos y retención. Al **activar** se genera el código, se
fija `published_on` y se dispara `ComplaintChannelPublished`. El **cartel** (`/admin/{tenant}/complaints/poster`) es una
página imprimible con el nombre de la empresa, el QR, la URL corta, «anónimo y confidencial» y un párrafo del
procedimiento. **Rotar el código** invalida el QR impreso (con confirmación y bitácora); **pausar** muestra «temporalmente
no disponible» con la alternativa externa.

### Enviar una queja (pública, sin cuenta)

URL `https://…/c/{code}`. Página móvil, «usted», un paso por pantalla y que **nunca avanza sola**:

1. **Qué quiere informar:** queja por una práctica (explicada con ejemplos), denuncia de violencia laboral, o aviso de un
   acontecimiento traumático severo (6.5). Texto de «cómo funciona» y los límites del anonimato, antes de escribir.
2. **Qué pasó:** descripción obligatoria (20–5000), fecha aproximada opcional (obligatoria en el aviso 6.5).
3. **Dónde y contra quién:** centro (opcional, con el aviso «en un centro pequeño esto puede acotar quién es»), «contra
   quién» (rol) y, si quiere, un texto libre. Las opciones sin cobertura se muestran deshabilitadas con la alternativa externa.
4. **Cómo quiere ir:** *Anónima* (seleccionada) o *Confidencial con mi nombre* (nombre obligatorio; contacto opcional).
   El aviso 6.5 **exige nombre** y lo explica: «sin su nombre no podemos canalizarle a atención ni cuenta como el escrito
   del numeral 6.5».
5. **Aviso por correo opcional**, con la frase de solo escritura.
6. **Revisar y enviar.** Éxito: se muestran **referencia** y **clave**, una vez, con «Copiar», la advertencia «si la pierde
   no podemos recuperarla» y «cierre esta pantalla en un dispositivo propio». No hay redirección ni sesión.

### Seguimiento (pública, con referencia y clave)

`/c/{code}/follow-up`: referencia y clave en un POST (nunca en la URL ni en una cookie). Responde lo mismo si la referencia
no existe o la clave es incorrecta (misma respuesta y mismo tiempo). Muestra estatus (Recibida, En revisión, Cerrada), la
resolución en palabras generales, cuántas personas atienden el caso (sin nombres), los mensajes de «Persona encargada» y
un cuadro para contestar o **retirar el caso**. Cada acción reenvía referencia y clave en el cuerpo; el navegador las
guarda solo en memoria.

### Atención (personas encargadas)

- **Bandeja** (`Empleados` del rediseño): casos asignados, paginados en el servidor, con filtros por estatus, tipo y centro,
  búsqueda por referencia y resumen de conteos arriba. Orden por `received_on` desc y luego `id` (aleatorio: el orden
  dentro del día no revela nada).
- **Caso** (`Canalizacion`): ficha con referencia, tipo, categoría, centro, `respondent_kind`, modo de identidad, fechas;
  el texto; personas con acceso; hilo con la persona; notas internas; acciones: acusar recibo, pasar a revisión, escribir
  a la persona, nota, **compartir** con otra encargada elegible, **excusarme**, retención legal, **cerrar** con resolución,
  **imprimir el caso** (acceso y exportación en bitácora).
- **Plazos:** el acuse (por omisión 3 días naturales) y la resolución (30) son recordatorios y criterio de Humetría, no
  plazos de la norma. Se muestran como «vencido» solo a las asignadas y como conteo a quien administra.
- **Notificaciones:** un barrido programado **cada hora** manda a cada encargada un correo con **solo conteos** («tiene 2
  casos nuevos y 1 con respuesta») y el enlace a la bandeja. No hay correo por envío: un correo al instante revelaría la
  hora exacta de un envío anónimo. El aviso de un 6.5 no es urgente por separado; el barrido es de una hora.

### Estado del canal y evidencia (owner o hr)

- **Estado:** activo desde, código, cobertura por opción de «contra quién» (quiénes, **solo como conteo**), plazos, casos
  sin encargada, casos con acuse vencido, resumen estadístico. 
- **Resumen** (`ComplaintStatistics`): totales por **tipo** y por **estatus**, con el mínimo `complaints.min_group` (5,
  como `results.min_group`) aplicado a cada celda y supresión complementaria; por debajo se lee «menos de 5». **Nunca**
  por centro, categoría ni «contra quién» para quien no es encargada del caso.
- **Evidencia 8.1 b** (`results/report`): una página imprimible con la configuración (fecha de publicación, tipos, procedimiento,
  plazos, retención, número de encargadas y si alguna es ajena a RH y a la dirección), la matriz de cobertura, el resumen
  con mínimo y la URL del canal. **Sin contenido de casos.** Ver o imprimir va a la bitácora.

## Integración con el portal y la política (gap 3)

El diseño paralelo de política y portal **consume** `Complaints\Contracts\ComplaintChannels` y escucha
`ComplaintChannelPublished/Paused/CodeRotated` para su evidencia de difusión (5.7 d). No importa nada de `Complaints` y
este módulo no importa nada de él. Reglas del trato:

- El portal **enlaza** a `ChannelData::url` con `rel="noreferrer"` (y mejor aún: muestra el QR o el texto). Nunca incrusta
  el formulario ni pasa el token de la persona. Una prueba de navegador del portal afirma el atributo; este módulo agrega
  una prueba de que ninguna ruta de `/c/*` lee `Referer`.
- El acuse de «difusión» es de la política: este módulo solo guarda `published_on`.
- Si el portal o la política necesitan algo más (por ejemplo, «el canal tiene al menos una encargada independiente») se
  agrega a `ChannelData` como booleano, nunca como lista de personas.

## Pantallas

Todas siguen el rediseño de `docs/design-system/pantallas/` (CLAUDE.md, «Design system»), en claro, oscuro y a 375 px.

| Pantalla | Quién | Referencia del rediseño |
|---|---|---|
| Canal: estado, lista de verificación, cobertura y resumen | owner, hr | `Eval-Activa` (ficha, pestañas con conteo) + `Ajustes` |
| Encargadas: designar y terminar | owner, hr | `Usuarios` + `Usuarios-Invitar` (panel lateral) |
| Cartel con QR | owner, hr | `results/report` (imprimible) |
| Evidencia 8.1 b | owner, hr | `results/report` |
| Bandeja de casos | encargadas | `Empleados` (tabla paginada) |
| Caso | encargadas asignadas | `Canalizacion` (revisión) y `Guia-I-Acontecimiento` |
| Formulario público | cualquiera con el código | `Encuesta-Movil` |
| Seguimiento de la persona | referencia y clave | `Encuesta-Movil` |
| Canal pausado, código inválido | cualquiera | `Enlace-Vencido` (404 neutro) |

- Toda lista va paginada en el servidor (bandeja y entradas largas del hilo). Las encargadas (pocas) y la matriz de
  cobertura viajan como resumen corto, cada prop de arreglo con su razón en `tests/Unit/PaginatedListsTest.php`. Los centros
  del formulario público son un catálogo corto (`WorkCenters`); si la empresa tiene decenas, se busca en el servidor
  (`serverSource`).
- Toda página de RH tiene su prueba de props: ninguna página fuera del expediente de un caso recibe texto de una queja.
  Toda página tiene su prueba de navegador con `visitPage()` (componente exacto, sin errores de JS, sin violaciones de CSP,
  axe).
- Menú: `Cumplimiento → Quejas y denuncias` para quien tenga `manage-complaint-channel` o `handle-complaints`.
- El riesgo no aparece aquí: no se califica ni se muestra `RiskLevel`; la gravedad no se infiere.
- Frontend modular: `pages/complaints/*`, `components/complaints/*`, `hooks/use-complaint-form.ts`, `lib/complaints/*`
  (formato de referencia y clave, validaciones del paso a paso) con Vitest ≥ 90%. Rutas con Wayfinder.

## Seguridad de la página pública

- Rutas fuera del grupo de sesión: sin `StartSession`, `ShareErrorsFromSession`, `ValidateCsrfToken`, `EncryptCookies` ni
  cookies en cola; un middleware Inertia propio que no toca la sesión y comparte solo el nonce. **Es el mayor riesgo
  técnico:** el primer commit de la página es una prueba que afirma «sin `Set-Cookie` y sin una fila nueva en `sessions`»,
  y se resuelve ahí antes de seguir. Plan B si el middleware de Inertia exige sesión: un `session.driver` de `array` solo
  para estas rutas y quitar `Set-Cookie`; es peor y se documenta si se usa.
- Envío y seguimiento por **POST JSON** (`useHttp`), sin redirección ni flash. Errores de validación en el JSON, sin
  devolver los valores. `dontFlash` agrega las llaves nuevas por si acaso.
- Encabezados de `SecurityHeaders` (CSP con nonce, sin orígenes nuevos) más `Cache-Control: no-store`, `Referrer-Policy:
  no-referrer` y `X-Robots-Tag: noindex, nofollow`. Los tests de navegador afirman `cspViolations()` vacío.
- Sin fuentes, scripts ni imágenes de terceros en `/c/*`. Sin analítica en estas rutas.
- Throttle por `RateLimiter::for('complaint-…')` con llaves HMAC (ver «Anti-spam»). Las respuestas 429 de estas rutas
  no redirigen (ya hay un manejador de `ThrottleRequestsException`; el constructor verifica que no cree sesión).
- Entradas en la lista pública de `tests/Feature/RouteSecurityTest.php`, cada una con su razón: `complaints.public.show`,
  `complaints.public.store`, `complaints.follow-up.show`, `complaints.follow-up.open`, `complaints.follow-up.messages.store`
  y `complaints.follow-up.withdraw`. El código del canal es la credencial de la **empresa**; la referencia con la clave es la
  credencial de la **persona**.

## Logs y bitácora

**Reglas:** el mensaje es un nombre fijo en pasado; los datos van en el contexto; **nunca** texto de una queja, nombre,
correo, clave, referencia, centro ni «contra quién» en una línea de log o en la bitácora.

- **Eventos de la persona (sin id del caso, a propósito):** `complaints.submitted` {tenant_id, channel_id, type,
  identity_mode}, `complaints.follow_up_opened` {tenant_id, channel_id}, `complaints.reporter_message_sent` {tenant_id,
  channel_id}, `complaints.withdrawn` {tenant_id, channel_id}, `complaints.spam_blocked` {channel_id, reason}. Un id de caso
  junto a la hora de la línea es un hilo de correlación.
- **Eventos de las encargadas (atribuibles por diseño):** `complaints.status_changed`, `complaints.message_sent`,
  `complaints.note_added`, `complaints.assignment_changed`, `complaints.recused`, `complaints.hold_set`, `complaints.closed`,
  `complaints.handler_designated`, `complaints.handler_ended`, `complaints.channel_activated`, `complaints.channel_paused`,
  `complaints.code_rotated`, `complaints.settings_updated`, `complaints.digest_sent` {conteo}, `complaints.purged` {conteo}.
  Llevan `complaint_id` y estados, nada de texto.
- **A la bitácora (`RecordAudit`, solo de acciones de usuarios; sujeto = el UUID del caso, metadatos solo enums y ids de
  usuario):** ver un caso (`complaint.viewed`), cambiar estatus, escribir a la persona, cambiar asignaciones, excusarse,
  retención legal, imprimir (`complaint.exported`), designar o terminar encargadas, activar, pausar y rotar el código,
  ver o imprimir la evidencia, y la purga (actor nulo). **No** se bitacorea el envío de la persona ni su seguimiento.
- Hoy ninguna pantalla lista `audit_entries`. Si se agrega una, los eventos `complaint.*` no se muestran a quien no esté
  asignada; una prueba lo fija desde ahora para que no se olvide.
- `RedactSensitiveData` agrega las llaves nuevas, cada una con su prueba: `access_key`, `reference`, `respondent_kind`,
  `respondent_text`, `involved_text`, `reporter_name`, `reporter_contact`, `reporter_email`, `body`, `ticket`. (`description`,
  `note`, `notes`, `email` e `ip` ya están.)
- `QueryException` ya se reporta sin valores. Los correos del módulo no llevan contenido: una prueba lo afirma.
- **Colas:** un job lleva solo ids. No hay job por envío (el barrido es programado), así que ni Horizon ni Redis guardan la
  hora de una queja.

## Retención y purga

Ver «Alternativas, 3». El comando `complaints:purge` corre diario (scheduler) y está en `ScheduleTest`. Es idempotente;
pasa por una transacción; no toca casos con `on_hold`, sin cierre, o dentro del plazo. Borra con `DELETE` y deja el
esqueleto con `UPDATE` (las tablas del módulo no tienen el trigger de solo inserción de `audit_entries`: el contenido debe
poder desaparecer, y la verdad inmutable de que pasó está en la bitácora).

## Criterios nuevos (se escriben en `criterios.md` al implementar)

Mismo formato (qué hacemos, qué dice la norma, por qué, fuente), con la numeración que corresponda en ese momento:

1. Mecanismo seguro y confidencial para quejas y denuncias · Norma (8.1 b, 6.4, 10.2) + Humetría.
2. La Comisión de seguridad e higiene puede ser receptora · Norma (6.4).
3. Quejas y denuncias: tipos y categorías · Interpretación (4.6, 4.12, 7.3, 8.1 c).
4. El escrito del numeral 6.5 exige el nombre · Norma (6.5).
5. Anonimato técnico y sus límites; seguimiento con referencia y clave · Humetría.
6. Enrutamiento por conflicto de interés y acceso por asignación · Humetría.
7. Sin hora, IP, navegador ni cookies del envío · Humetría (sustentado en 8.1 b).
8. Estadísticas con mínimo por grupo · Humetría (como C-23).
9. Retención y purga · Humetría (por analogía con 10.4).
10. Plazos de acuse y resolución · Humetría.

## Decisiones del diseñador

El usuario puede corregir cualquiera al leer:

1. **Módulo propio `Complaints`**; las quejas no viven en `Responses`.
2. **Encargadas designadas, no «owner y RH ven todo».** La queja contra RH o contra el dueño es el caso de uso central
   del mecanismo, no un borde (ver alternativa E).
3. **Opción de «contra quién» sin cobertura = no se acepta**, con alternativa externa, en vez de entregarla a la persona señalada.
4. **Acceso por asignación explícita**, con 404 para todo lo demás y sin acceso retroactivo de designadas nuevas.
5. **Rol nuevo `complaints`** para encargadas externas, con la misma restricción de rutas que `committee`.
6. **Sin `created_at`, `updated_at` ni hora** en el caso ni en los mensajes de la persona; fechas por día; orden por `position`.
7. **Sesión y cookies ausentes** en las rutas públicas; el CSRF es un boleto firmado sin estado.
8. **Referencia (visible) + clave (secreta, solo su hash)**; sin recuperación de clave.
9. **Cifrado de columnas de texto** (`encrypted`) con la llave de la app. Protege copias de seguridad y accesos a la base; **no
   protege de la aplicación** (para eso es la autorización). Requiere `APP_PREVIOUS_KEYS` al rotar la llave.
10. **Correo de aviso de solo escritura**, opcional, nunca visible, borrado 30 días tras el cierre.
11. **Notificaciones en barrido horario con solo conteos**, no por envío.
12. **El aviso 6.5 no se acepta anónimo** porque el numeral exige el nombre.
13. **Sin adjuntos**; sin CAPTCHA de terceros; sin búsqueda por texto libre.
14. **Estadísticas solo por tipo y estatus** con mínimo 5; nunca por centro, categoría ni «contra quién».
15. **Retención configurable 12–60 meses (24 por omisión)**, esqueleto sin datos personales y retención legal manual.
16. **La plataforma no concluye.** La resolución es la clasificación de la persona encargada y se rotula así.
17. **La landing** dice que hay un «canal de quejas». El copy de «anónimo» solo se usa con los límites a la vista; se revisa
    antes del lanzamiento (copy compartido de otro PR; no se cambia en este trabajo).

## Requisitos de producción

- **Limitador en Redis, no en la tabla `cache`:** `CACHE_LIMITER=redis` (o el equivalente de `config/cache.php`) en
  producción. Con `CACHE_STORE=database` las llaves de límite quedarían en una tabla con su expiración exacta.
- **Registros del servidor web, proxy o CDN:** guardan IP, user-agent y `Referer` **fuera de la aplicación**. Para `/c/*`:
  no registrar el `Referer`, ni el cuerpo, y conservar los registros de acceso el menor tiempo posible (≤ 14 días). Si se usa
  Laravel Cloud u otro proveedor, confirmar su política antes de prometer nada en pantalla. Esto no altera el modelo de
  amenaza (el operador está fuera) pero la copia de la página no debe decir más de lo que se cumple.
- **Observadores de peticiones** (Telescope, Pulse, Nightwatch, Sentry u otros) **no** deben capturar cuerpos ni cabeceras
  de `/c/*`. Una prueba de configuración afirma lo que la aplicación controla.
- **Horizon y scheduler corriendo:** el barrido horario, la purga diaria y los correos van por cola/scheduler.
- **`APP_PREVIOUS_KEYS` al rotar `APP_KEY`:** el cifrado de columnas y los boletos firmados dependen de la llave.
- **Dependencia para el QR:** el SVG del QR se genera en el servidor con `bacon/bacon-qr-code`, que ya está instalada como
  dependencia de Fortify (2FA). Usarla directamente exige declararla en `composer.json`; **requiere aprobación**
  (CLAUDE.md, regla 5; pregunta 11).
- Configuración nueva → `config/complaints.php` y `.env.example`: `min_group`, límites de frecuencia, longitudes, plazos por
  omisión, límites de retención, datos y fuente de la alternativa externa (STPS-1 pregunta 10, fecha de consulta).

## Pruebas

Todo con TDD estricto (CLAUDE.md). Cada comportamiento: camino feliz, bordes, fallos.

- **Privacidad de logs y bitácora (la prueba central):** una prueba envía una queja con **cadenas marcadoras únicas** en
  cada campo (descripción, texto de la persona señalada, nombre, contacto, correo), usa una IP y un user-agent marcadores, abre
  el seguimiento, manda un mensaje de la persona, atiende el caso con una encargada (notas, mensaje, cierre) y corre el
  barrido y la purga; captura **todas** las líneas de log de **todos** los canales con un handler de prueba y afirma que
  **ninguna** contiene los marcadores, la clave, la referencia (en eventos de la persona), el correo ni la IP; afirma lo
  mismo sobre `audit_entries` (metadatos serializados), sobre los correos enviados (`Mail::fake`) y sobre los payloads de
  los jobs encolados (`Queue::fake`). Falla si se agrega una llave sensible sin redactar.
- **Sin rastro técnico:** el envío anónimo no emite `Set-Cookie`, no crea fila en `sessions`, no crea filas en `cache` con la
  dirección en claro, y `complaints` y `complaint_entries` no tienen columnas `timestamp` salvo `occurred_at` de las
  encargadas (prueba de esquema). Ninguna columna del módulo es llave a empleado, invitación, token del portal, campaña o
  respuesta (prueba de esquema).
- **Identificadores:** `id` UUID v4 (versión y variante); `reference` único por empresa; colisión de referencia reintenta;
  solo se guarda el hash de la clave (la clave en claro no aparece en ninguna tabla).
- **Aislamiento de tenant:** A no lee ni modifica lo de B, por consulta y por URL (404), para cada modelo; código de otra
  empresa o empresa bloqueada → 404 neutro.
- **Enrutamiento y acceso:** queja «contra RH» no se asigna a `hr`; «contra dirección» no se asigna a `owner`; el resto a
  todas las del alcance; alcance por centro; sin encargadas independientes la opción no se acepta (422) y el formulario la
  muestra deshabilitada; encargada designada después **no** ve casos previos (404); owner no asignado → 404 en el caso; los
  roles `viewer` y sin permiso → 403 en las rutas de administración; excusarse termina la asignación y el caso queda en el
  conteo si no hay otra; asignar a quien el conflicto excluye → rechazado; `committee` y `complaints` solo ven rutas
  `tenant.complaints.*`; impersonación → bloqueado.
- **Formulario y validación:** descripción 19 → rechazada, 20 → ok, 5000 → ok, 5001 → rechazada; aviso 6.5 sin nombre →
  rechazado; modo anónimo con nombre → rechazado (y el `check` de base); categoría incoherente con el tipo → rechazada;
  centro de otra empresa → rechazado; `ticket` de menos de 3 s o de más de 2 h → rechazado; honeypot lleno → éxito falso sin
  fila; correo opcional se guarda cifrado y nunca sale en props.
- **Seguimiento:** referencia válida + clave correcta abre; clave incorrecta y referencia inexistente dan la **misma**
  respuesta; sin sesión; la persona no ve notas internas ni al autor de los mensajes; retirar cierra con `withdrawn`;
  mensaje en caso cerrado → rechazado hasta reabrir; límite de consultas exacto (30 pasa, 31 → 429).
- **Anti-spam y límites:** 5 envíos por hora por dirección y el sexto → 429 (límite exacto); dos direcciones distintas no
  se afectan; tope diario por canal; las llaves del limitador son HMAC (la IP en claro no está en ninguna llave).
- **Propiedades de las props:** cada página de RH afirma que sus props no traen texto de queja salvo el expediente del caso
  y no traen `access_key_hash`, `reporter_email` ni (en modo anónimo) campo de identidad; la matriz de cobertura solo trae
  conteos; la evidencia no trae contenido.
- **Estadísticas:** 4 casos → «menos de 5»; 5 → se muestran; supresión complementaria (una celda oculta con total visible
  se oculta también); sin desgloses por centro, categoría o «contra quién».
- **Notificaciones:** el barrido manda un solo correo con conteos, no se repite por la misma entrada (`notified_position`),
  y el correo no contiene contenido ni referencia; el aviso a la persona (si dejó correo) no lleva referencia.
- **Retención:** 23 meses 29 días → no purga; 24 meses → purga; caso con retención legal → no purga; caso sin cerrar →
  no purga; el correo se borra a los 30 días del cierre; el esqueleto conserva tipo, categoría, resolución y mes; la
  purga es idempotente y no toca otra empresa.
- **Seguridad HTTP:** encabezados de `SecurityHeaders` más `no-store`, `no-referrer` y `noindex`; sin orígenes nuevos en la
  CSP; entradas nuevas en `RouteSecurityTest` con su razón; `Referer` ignorado.
- **Integración:** `ComplaintChannels::current()` devuelve solo `ChannelData` publicable; los eventos se despachan al
  activar, pausar y rotar; no hay evento de queja recibida; `Assistant` y la búsqueda ⌘K no devuelven contenido de quejas;
  `ArchitectureTest` y `FrontendArchitectureTest` en verde.
- **Mutación:** `mutates()` en las acciones con reglas: enrutamiento por conflicto, asignación, estatus, retención/purga,
  estadísticas con mínimo, generación y verificación de clave, límites. Se corre semanal; no en local por iniciativa propia.
- **Consultas:** la bandeja y el caso afirman su número de consultas (sin N+1).
- **Navegador (móvil 375 px y escritorio, claro y oscuro):** enviar anónima y ver referencia y clave; volver con la clave,
  leer y contestar; la encargada responde y cierra; el canal pausado; cada página con `visitPage()` (axe y `cspViolations()`
  vacío); sin cookies de la aplicación tras enviar.
- **Documentos:** `criterios.md` y `gaps-pendientes.md` actualizados, y `docs/testing.md` si cambia algo del arnés.

## Tamaño y descomposición en commits

**Tamaño: L** (como estima `gaps-pendientes.md`): un módulo nuevo, un rol nuevo en `Tenancy`, dos superficies públicas sin
sesión y la prueba de privacidad. Un PR para todo el trabajo, con commits atómicos; cada uno deja el sistema en verde.
Orden propuesto (un comportamiento por commit, prueba primero):

1. **Esqueleto y permisos:** módulo `Complaints`, `config/complaints.php`, permisos `manage-complaint-channel` y
   `handle-complaints` (migración), pruebas de arquitectura.
2. **Rol `complaints`** en `Tenancy` (enum, invitación, restricción de rutas generalizada) y su prueba.
3. **Canal:** activar, pausar, rotar código, plazos, procedimiento; tenant, bitácora y logs; contrato `ComplaintChannels` y
   los tres eventos.
4. **Encargadas:** designar y terminar con alcance por centro; correo a las demás; matriz de cobertura de «contra quién».
5. **Página pública sin sesión (spike con prueba):** `GET /c/{code}`, sin cookies ni sesión, encabezados, CSP, límites,
   `RouteSecurityTest`.
6. **Enviar una queja anónima:** `POST`, referencia y clave (solo hash), boleto firmado y honeypot, `received_on`, logs de la
   persona sin id.
7. **Modo confidencial y aviso 6.5:** nombre, contacto, fecha de ocurrencia, involucrados; reglas y `check`s; cifrado.
8. **Enrutamiento y asignaciones:** conflicto de interés, alcance por centro, 404 por asignación, casos sin encargada.
9. **Bandeja y caso (lectura):** paginada, filtros, props sin texto fuera del caso, consultas, bitácora de lectura.
10. **Atención:** acuse, estatus, notas, mensajes, compartir, excusarse, retención legal, cerrar, imprimir.
11. **Seguimiento de la persona:** consulta con referencia y clave, respuesta uniforme, mensajes, retirar, límites.
12. **Barrido horario de avisos** (solo conteos) y recordatorios de plazos.
13. **Retención:** `complaints:purge`, esqueleto, correo a 30 días, `ScheduleTest`.
14. **Estado del canal, resumen con mínimo y evidencia 8.1 b.**
15. **Cartel con QR** (depende de la pregunta 11).
16. **Aviso por correo de solo escritura** (opcional, descartable; pregunta 6).
17. **Prueba global de privacidad** (logs, bitácora, correos, colas, esquema), nuevas llaves en `RedactSensitiveData` y
    cierre de documentos (`criterios.md`, `gaps-pendientes.md`, `docs/testing.md`).

Los tests de navegador y de `visitPage()` entran con el commit de cada pantalla.

## Lo que queda al constructor

Nombres finales de tablas, columnas, clases y rutas; textos de UI y de correo en español (**tuteo en el panel**, «usted»
en la página pública); redacción del procedimiento modelo, de los avisos de límites del anonimato y de las preguntas;
alfabeto exacto de la referencia y la clave; el middleware Inertia sin sesión (y si Plan B hace falta); cómo se generaliza
la restricción de rutas de `committee`; si `Members` o `WorkCenters` necesitan métodos nuevos; el plazo exacto del
limitador por dirección y del tope por canal (con los valores de la tabla como piso); y cómo encaja el cartel con el QR
según la pregunta 11.

## Pendiente

Nada impide aprobar. Quedan **cinco cosas que valida una persona con criterio legal antes de lanzar**, no el constructor: el
texto del aviso de privacidad y de los límites del anonimato; la retención por omisión (24 meses) y su piso; que un canal
anónimo sea suficiente frente a otras obligaciones de la empresa (por ejemplo protocolos de acoso sexual u obligaciones
fuera de la NOM-035, que este diseño no cita); el modelo de procedimiento; y el texto de «sin garantía de protección
contra represalias» que no prometemos.

## Preguntas abiertas para el usuario

Responde todas juntas; cada una trae mi recomendación por defecto («usa los defaults» las acepta todas).

1. **¿Quién recibe las quejas?** *Default:* personas encargadas designadas por la empresa (por empresa o por centro), con el
   usuario que activa el canal designado por omisión, y acceso por asignación explícita a cada caso. Alternativa: owner y
   RH ven todo (más simple, pero falla contra RH o el dueño).
2. **Si una opción de «contra quién» (RH o dirección) no tiene a nadie independiente que la reciba, ¿el formulario la
   rechaza y ofrece la vía externa de la STPS?** *Default:* sí, la rechaza y lo avisa en el panel. Alternativa: la acepta
   y la entrega al dueño o a RH (no la recomiendo).
3. **¿Creamos un rol nuevo `complaints` («Atención de quejas») para encargadas externas (abogado, persona de confianza,
   sindicato)?** *Default:* sí, con acceso solo a las rutas del canal. Alternativa: solo owner, hr y committee pueden ser encargadas.
4. **¿La Comisión de seguridad e higiene puede ser encargada del canal (6.4 lo permite)?** *Default:* sí, por centro.
5. **Anonimato:** ¿anónima por omisión, más la opción «confidencial con mi nombre» que solo ven las encargadas, y nunca un
   modo «identificada para todos»? *Default:* sí. El aviso 6.5 exige nombre y no se acepta anónimo.
6. **Seguimiento:** ¿referencia + clave sin recuperación, más el **aviso por correo de solo escritura** opcional (nadie lo
   ve, se borra 30 días después de cerrar)? *Default:* sí a ambos; el correo va en un commit aparte y se puede quitar sin
   tocar lo demás.
7. **Tiempo:** ¿guardamos solo la fecha (sin hora) en el caso y en los mensajes de la persona, y ordenamos el hilo con un
   consecutivo? *Default:* sí. Costo: los plazos se miden en días.
8. **Texto cifrado en la base** (`encrypted` con la llave de la app, sin búsqueda por texto libre). *Default:* sí. Protege
   copias y accesos a la base, no a la aplicación; exige `APP_PREVIOUS_KEYS` al rotar la llave.
9. **Retención:** ¿24 meses después del cierre por omisión, configurable de 12 a 60, con retención legal manual y un
   esqueleto sin datos personales al purgar? *Default:* sí; el plazo lo valida un abogado antes del lanzamiento.
10. **Adjuntos:** ¿fuera de esta entrega por los metadatos que identifican? *Default:* sí, fuera.
11. **QR:** ¿apruebas declarar `bacon/bacon-qr-code` (ya instalada por Fortify) en `composer.json` para generar el QR del
    cartel en el servidor? *Default:* sí. Alternativa: cartel solo con la URL en esta entrega y QR después.
12. **Anti-spam:** ¿sin CAPTCHA de terceros, con límites por dirección con hash (5 envíos por hora), tope diario por canal
    (200), honeypot y boleto firmado? *Default:* sí. La prueba de trabajo queda para si hay inundaciones reales.
13. **Estadísticas para quien administra el canal sin ser encargada:** ¿solo totales por tipo y estatus, con mínimo 5, y
    nunca por centro, categoría ni «contra quién»? *Default:* sí.
14. **Avisos a las encargadas:** ¿un barrido cada hora con solo conteos, en lugar de un correo por cada envío?
    *Default:* sí. Costo: hasta una hora de latencia, también para un aviso 6.5.
15. **Plazos por omisión** de acuse (3 días naturales) y resolución (30), rotulados como criterio de Humetría y no como
    plazo de la norma. *Default:* sí, configurables.
16. **Centro de trabajo en el formulario:** ¿enlace y QR únicos por empresa, con centro **opcional** y el aviso de que en un
    centro pequeño acota quién es? *Default:* sí. Alternativa: un enlace por centro (más fácil de acotar, más cartelería).
17. **Bloquear las rutas del canal durante la impersonación del superadmin**, aunque el operador esté fuera del modelo.
    *Default:* sí.
18. **Orden de trabajo:** ¿se implementa antes o después del registro de canalización (gap 1) y del programa (gap 2)?
    *Default:* después del gap 1 (comparten el tema de la persona canalizada) y sin depender de él: la persona encargada
    anota a mano hasta que exista el evento público.

## Decisiones del usuario (2026-11-03)

Se aceptaron **todos los valores por defecto** de «Preguntas abiertas», incluida la declaración de
`bacon/bacon-qr-code` en `composer.json` cuando el diseño la necesita (dependencia ya presente por Fortify).
Orden de implementación: Programa de intervención y Canal de quejas (en paralelo), Política y portal al final.
