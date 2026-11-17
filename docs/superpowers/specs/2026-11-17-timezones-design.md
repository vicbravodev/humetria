# Zonas horarias: cada centro de trabajo con la suya

**Estado:** Decidido por Victor el 2026-11-17 (ver §6). Nada construido. Nace de la revisión de las pantallas de la persona trabajadora
(`docs/ui-polish-workers.md`, hallazgo 23). Es trabajo propio y de varios módulos: va en su propio PR, con TDD.

## 1. El problema, con evidencia

Hoy toda la plataforma usa **una sola zona**: `config('app.display_timezone')` (`America/Mexico_City`), con **197 usos** repartidos por
Attendance, Billing, Telework, Seating, Compensation, Tenancy, Disclosure, Complaints y más, y los 25 comandos programados
(`routes/console.php`) corren a la hora de esa zona. Ni `work_centers` ni `tenants` tienen una columna de zona. Lo que sí está bien: las
marcas y los demás instantes se guardan en UTC (`timestampTz`) y la hora de la marca la pone el servidor.

Consecuencias para un cliente con centros fuera de la Zona Centro (México tiene cuatro husos):

| Centro | Zona real | Lo que hoy pasa |
|---|---|---|
| Cancún (Quintana Roo) | UTC−5 todo el año | Una entrada a las 06:00 locales se muestra a las **05:00**; el «día» de la persona empieza una hora antes. |
| Monterrey, Mérida, Ciudad de México | UTC−6 | Correcto (es la zona global). |
| Hermosillo, Mazatlán | UTC−7 | Se muestra **una hora más tarde**. |
| Tijuana (Baja California) | UTC−8, con horario de verano en la frontera | Se muestra **2 h más tarde en invierno y 1 h en verano**. |

Efectos más graves, porque la jornada es un registro con valor probatorio (art. 132, XXXIV, LFT; `docs/normas/jornada/criterios.md`):

- **Retardos.** `WorkedDay::lateMinutes` compara el instante de la marca con la hora del turno armada en la zona global: una persona de
  Tijuana que entra a su hora (06:00 locales) sale con **120 minutos de retardo**; una de Cancún, con **60 minutos de adelanto**.
- **Días y semanas.** `PunchDay`, `PersonWeek`, `EffectiveMarks` y los reportes parten el día a la medianoche de la zona global.
- **Comandos programados.** `attendance:close-days` corre a las 00:15 de Ciudad de México, cierra el «antepenúltimo» día (deja un día de margen)
  **con los límites de día de Ciudad de México** y abre para confirmar «la semana que terminó»: en Tijuana la abre el domingo a las 22:15, con el
  domingo sin acabar, y las horas de un turno de tarde o noche se reparten entre días en el corte equivocado (lo que mueve el cálculo de horas extra
  por día). Los resúmenes semanales y recordatorios llegan a la hora de la zona global (a las 9:00 de Ciudad de México son las 7:00 en Tijuana y las
  10:00 en Cancún). Las encuestas que «cierran el 4 de diciembre» terminan a las 22:00 de ese día en Tijuana.
- **Fechas sin hora** (cierre de campaña, vacaciones, plazos de denuncias): «cierra el 4 de diciembre» no dice en qué zona termina el día.

## 2. Lo que dice la ley (verificado el 2026-11-17 en el texto vigente de la ley y en tzdata)

- Fuente: **Ley de los Husos Horarios en los Estados Unidos Mexicanos** (DOF 28-10-2022, en vigor el 30-10-2022; única reforma DOF
  29-11-2022), texto vigente de la Cámara de Diputados: <https://www.diputados.gob.mx/LeyesBiblio/pdf/LHHEUM.pdf> (reformas:
  <https://www.diputados.gob.mx/LeyesBiblio/ref/lhheum.htm>). La tzdata (archivo `northamerica`, <https://github.com/eggert/tz>) da las
  mismas listas para `America/Matamoros`, `America/Ojinaga` y `America/Ciudad_Juarez`.
- Ya no hay horario de verano general; el **horario estacional** solo aplica en la frontera norte (art. 5), de las 2:00 del segundo domingo
  de marzo a las 2:00 del primer domingo de noviembre (en 2026: 8 de marzo y 1 de noviembre):
  - Coahuila: Acuña, Allende, Guerrero, Hidalgo, Jiménez, Morelos, Nava, Ocampo, Piedras Negras, Villa Unión y Zaragoza; Nuevo León:
    Anáhuac; Tamaulipas: Nuevo Laredo, Guerrero, Mier, Miguel Alemán, Camargo, Gustavo Díaz Ordaz, Reynosa, Río Bravo, Valle Hermoso y
    Matamoros → `America/Matamoros` (UTC−6, en verano UTC−5).
  - Chihuahua: Coyame del Sotol, Ojinaga y Manuel Benavides → `America/Ojinaga` (UTC−6 / UTC−5).
  - Chihuahua: Janos, Ascensión, Juárez, Praxedis G. Guerrero y Guadalupe → `America/Ciudad_Juarez` (UTC−7 / UTC−6).
  - Todo Baja California → `America/Tijuana` (UTC−8 / UTC−7).
- Sin horario estacional: Quintana Roo (Zona Sureste, UTC−5), Sonora y el resto del Pacífico (UTC−7), el resto de Chihuahua (Zona Centro,
  `America/Chihuahua`) y Bahía de Banderas, que la ley deja en la Zona Centro aunque Nayarit esté en el Pacífico (art. 3, fr. II).
- La ley nombra los meridianos de forma dispareja (75° para Coahuila en verano, 90° y 105° para Chihuahua, que son los de su hora normal);
  se lee con su propio texto, «acorde a sus correlativos a su franja fronteriza»: cada municipio sigue a la ciudad de EE. UU. de enfrente,
  que es como lo lee la tzdata.
- **Conclusión de diseño:** no escribimos reglas de horario de verano ni listas de municipios en nuestro código. Guardamos el **identificador IANA**
  de la zona (`America/Tijuana`) y dejamos que la base de datos de zonas horarias del sistema (tzdata/ICU) conozca sus reglas y sus cambios.

## 3. Decisiones propuestas

1. **Los instantes siguen en UTC** (`timestampTz`). No cambia. Es lo correcto.
2. **La zona es del centro de trabajo y se elige sola.** La jornada, el turno y el cumplimiento se miden en el centro. Nueva columna
   `work_centers.timezone` (IANA, `not null`, con `CHECK` contra la lista permitida). **Se propone automáticamente** a partir del estado
   (`MexicanState`, que ya es un catálogo en `work_centers.state`) y del municipio, con una tabla de datos (no código) para los estados con más de una
   zona (Baja California, Chihuahua, Tamaulipas y Nayarit, entre otros) y para la franja fronteriza con horario estacional. La pantalla del centro
   **muestra la zona elegida** («Hora del Pacífico, UTC−7 · Hermosillo») con «Cambiar», y nunca la deja en silencio: un error aquí es un error
   en un registro legal. Quien edita el estado o el municipio ve la zona recalculada antes de guardar. La zona del teléfono de la persona no se
   usa nunca (viaja, el teléfono se desajusta; la hora ya la pone el servidor).
3. **La empresa tiene una zona por omisión** (`tenants.timezone`, IANA, `not null`): hereda los centros nuevos y se usa en lo que es de la
   empresa y no de un centro (cobros, resúmenes al dueño). `app.display_timezone` queda solo como valor inicial de esa columna.
4. **Cada marca guarda la zona del lugar donde se tomó** (`attendance_marks.timezone`, IANA). Las marcas son de solo inserción y son el
   registro legal: si un centro cambia de zona o la ley cambia, **las marcas viejas no se mueven de día**. La zona de la marca es la del centro
   cuyo QR o geocerca coincidió con la marca (puede ser **otro centro de la misma empresa**, por ejemplo en un viaje de trabajo); si no coincidió con
   ninguno, o la marca es remota, es la del centro de origen de la persona. Las correcciones llevan la zona de la marca que corrigen.
5. **Se elige por ciudad, no por estado**, en una lista corta de zonas de México (IANA): `America/Cancun`, `America/Mexico_City`,
   `America/Monterrey`, `America/Merida`, `America/Chihuahua`, `America/Hermosillo`, `America/Mazatlan`, `America/Tijuana`,
   `America/Matamoros`, `America/Ciudad_Juarez`, `America/Ojinaga`. Se validan con `DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX')`.
   Una empresa con un centro fuera de México (no hay hoy) pediría ampliar la lista: decisión aparte.
6. **Las horas trabajadas se calculan con los instantes en UTC**, nunca restando horas del reloj de pared. El día de 25 horas (1 de noviembre en
   Tijuana) tiene una hora repetida; el turno 22:00 a 06:00 que lo cruza dura 9 horas, no 8.
7. **Las fechas sin hora se leen en la zona del centro** (o de la empresa si no hay centro): «cierra el 4 de diciembre» termina a las 23:59:59
   de ese día en esa zona.
8. **Reportes y exportaciones** muestran la hora local con su desplazamiento («06:00 UTC−06:00») y el nombre de la zona; la hora repetida
   de un cambio de horario siempre lleva su desplazamiento.

9. **Marcar fuera de su centro no es un retardo.** El turno de una persona está en la zona de su centro de origen (`starts_by_weekday` es hora de
   pared de ese centro). Si marca en **otro centro** (otra zona) o **fuera de toda zona** por un viaje, la marca queda con la zona donde se
   tomó, se **señala como «fuera de su centro»** (la política de zona ya existe: «permitir y señalar») y **no se calcula retardo** contra su turno;
   las horas del día se siguen calculando con los instantes. A la persona se le muestra esa marca con la hora del lugar («14:00 · hora de
   Tijuana»). Después se podrá registrar una **comisión** (RH anota que del 3 al 5 está en otro centro y su turno se mide allí): fuera de este diseño.
   Sin esto, una persona de Monterrey que marca en Tijuana a su hora local saldría con dos horas de retardo o de adelanto.
   **Hoy el QR de otro centro se rechaza** con «ya venció» (`qr_invalid`, hallazgo 26 de `ui-polish-workers.md`): esto exige que el servidor distinga
   `qr_expired`, `qr_other_center` y `qr_invalid`, y que `qr_other_center` se acepte señalado cuando la política lo permite.

## 4. Cómo se construye (por fases, TDD; cada fase en verde y revertible)

0. **Contrato `Timezones`** (módulo Organization o Tenancy, API pública): `forCenter(string $workCenterId): string`,
   `forTenant(): string`, `now(string $zone)`, `startOfDay`, `toLocal`. Una prueba de arquitectura que prohíbe `config('app.display_timezone')`
   fuera de ese contrato y de la migración inicial (como las demás reglas de `ArchitectureTest`).
1. **Datos:** migraciones de `work_centers.timezone`, `tenants.timezone` y `attendance_marks.timezone` (sin clientes todavía: sin compatibilidad
   hacia atrás; todo lo existente queda en `America/Mexico_City`). Alta y edición de centros con el selector de zona. Prueba de que el trigger
   de solo inserción de las marcas no cambia.
2. **Attendance** (lo más importante y lo que tiene valor probatorio): `RecordMark` guarda la zona del centro; `PunchDay`, `PersonWeek`,
   `EffectiveMarks`, `WorkedDay` (retardo y horas), `CorrectionPreview/Pages`, `ReportPages`, el quiosco y las pantallas leen la zona de la marca o
   del centro. La fecha de «Marca registrada» sale de `marked_at` (hallazgo 20).
3. **Programación (estándar de la industria para multi-zona):** un **despachador cada 15 minutos** que, por cada comando, busca los centros (o
   empresas) cuya hora local ya pasó la hora objetivo y que aún no lo corrieron **ese día local**, y encola un trabajo por centro. Un registro
   `unique(centro, comando, fecha_local)` lo hace idempotente (exactamente una vez por día local), tolera un tick perdido (el siguiente se pone al
   día) y un reintento, y no depende de que el servidor esté en una zona. Los comandos de la empresa (cobros, conciliación) usan la zona de la
   empresa. `attendance:close-days` y los recordatorios y resúmenes por centro pasan a este esquema. La hora objetivo es la local del centro.
4. **Resto de módulos:** Leave (derechos y días a partir de «hoy» del centro), Complaints (plazos), Campaigns/Engagement (cierres), Telework, Seating,
   Disclosure, Compensation (si calcula por fecha). Un módulo por vez, cada uno con su prueba de otra zona.
5. **Mantenimiento de tzdata:** el contenedor y PHP/ICU se reconstruyen con regularidad; una prueba en CI fija conversiones conocidas (Cancún,
   Hermosillo, Tijuana antes y después del cambio del 1 de noviembre de 2026) y falla si el sistema trae reglas viejas.

## 5. Pruebas de estrés (se escriben primero)

- Mismo instante, cinco zonas: la hora local, el día, la semana y el retardo de cada centro.
- Retardo: entrada a la hora del turno en Cancún, Monterrey, Hermosillo y Tijuana debe dar 0 minutos.
- Marca a las 23:59:59 y a las 00:00:07 del centro, con el reloj del teléfono desajustado y en otra zona.
- Turno nocturno 22:00 a 06:00 en Tijuana el 1 de noviembre de 2026 (día de 25 horas) y el 8 de marzo (de 23): las horas trabajadas salen de UTC.
- Hora repetida del cambio de horario: dos marcas «01:30» distintas, ordenadas por UTC, con su desplazamiento en el reporte.
- Cruce de semana (domingo a lunes) y de año; semana ISO de un centro y del otro.
- Una persona que pasa de Monterrey a Tijuana en la semana: cada marca en su zona; la semana se muestra en la zona del centro actual.
- Un centro que cambia de zona: las marcas viejas no cambian de día.
- Fechas sin hora: cierre de campaña, vacaciones y plazos en el centro más al oeste y el más al este.
- Comandos programados: cada centro procesado una sola vez por día local, aunque el comando corra cada 15 minutos o se reintente.
- Reloj del servidor: la hora de la marca nunca sale del teléfono. El reloj del quiosco y el de «Marcar» (`useNow`) toman la diferencia respecto a la
  hora del servidor y muestran la zona del centro: con la tableta o el teléfono con la hora mal, la pantalla coincide con lo que se registra.

## 6. Decisiones (Victor, 2026-11-17)

1. **La zona va por centro de trabajo y se elige sola** a partir del estado y el municipio, siempre visible y con «Cambiar». ✅
2. **La zona se guarda en cada marca**, y es la del lugar donde se marcó: si alguien sale de su estado por trabajo y marca allá, no es un
   retardo (§3, punto 9). ✅
3. **Lista de zonas de México.** ✅
4. **Programación:** despachador cada 15 minutos con registro idempotente por centro y día local (la elección de Claude como estándar de la
   industria para multi-zona, aprobada por Victor). ✅
5. **Se hace antes** de construir el pulido de asistencia, para que lo nuevo se construya sobre la base correcta. ✅

**Orden de trabajo:** primero esta base (fases 0 a 3 y Attendance), en su propio PR con TDD; después el pulido de las pantallas de asistencia.
La tabla de excepciones por municipio quedó confirmada contra el texto vigente de la ley (§2) el 2026-11-17.

## 7. Riesgos y lo que queda fuera

- La tabla de municipios es la de la ley a la fecha (§2); si la ley cambia, se actualizan la tabla y su prueba, y la tzdata del sistema las reglas.
- El municipio se escribe a mano: quien escribe la ciudad («Ciudad Juárez», «Ciudad Acuña») en vez del municipio («Juárez», «Acuña») recibe la
  zona del estado; la pantalla muestra la zona con «Cambiar».
- No es asesoría legal; el valor probatorio de la marca lo valida el abogado de cada empresa (criterios de jornada 5.x).
- Fuera de alcance: centros fuera de México, horarios de verano de otros países, y mostrar a la persona la hora de otro centro.
