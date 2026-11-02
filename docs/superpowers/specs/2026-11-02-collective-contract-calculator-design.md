# Calculadora de contrato colectivo: costeo de revisiones salariales y contractuales

Fecha: 2026-11-02. Estado: propuesta (pendiente de aprobación). Va **después de Ley Silla**; no se construye antes.

## Intención

Una revisión de contrato colectivo se negocia con dos números ("X % al salario, Y % en prestaciones"), pero lo que
cuesta cada punto es difícil de ver: un aumento al salario arrastra aguinaldo, vacaciones, prima vacacional, fondo de
ahorro, cuotas del IMSS, aportación al Infonavit e impuesto sobre nómina; una prestación nueva cuesta distinto que un
punto de salario; y con los años pesa más porque la antigüedad sube sola y crecen las obligaciones por antigüedad y
jubilación.

Humetria ya es una plataforma de herramientas de RH con **BI además de cumplimiento**. Esta herramienta pone a quien
negocia (RH de la empresa o el sindicato) frente a un **reporte completo del impacto de una propuesta, a corto y a
largo plazo**, con todos sus supuestos a la vista, para que la mesa discuta con números y no con intuición.

La usan **los dos lados**: una empresa la usa con su padrón y su tabulador; un sindicato la contrata **solo para
esto**, con el padrón de salarios que la empresa le entrega para negociar, y apoya con ella su postura. Cada lado
trabaja en su propia cuenta; nunca ven los datos del otro.

## Decisiones del usuario (2026-11-02)

1. Es una herramienta para **empresa y sindicato**. Un sindicato puede contratar Humetria solo para la calculadora.
2. **Un solo padrón y un solo tabulador**: el empleado tiene una relación con su categoría y nivel del tabulador. El
   sindicato carga su padrón con el mismo importador de empleados que usa la empresa; no hay un segundo modelo.
3. **Se relaja el modelo del padrón** para que acepte lo que una cuenta de sindicato puede recibir (sin contacto, sin
   centro de trabajo, etc.). Ver "Padrón".
4. **Dos identificadores por persona**: el nuestro, interno (`id`, UUID), y el de la empresa (`employee_number`, ya
   existe), que es el que aparece en los reportes que se comparten, porque es el que ellos conocen.
5. **Permisos propios** para ver y para administrar los datos de compensación; el dinero no se ve con el rol de
   consulta ni con el de la Comisión.
6. **Prestaciones**: un catálogo base (aguinaldo, vacaciones, prima vacacional, fondo de ahorro, vales de despensa,
   prima de antigüedad, prima dominical, bonos de productividad y de asistencia/puntualidad, gastos médicos mayores,
   fondo de jubilación) **y prestaciones a la medida** definidas por sus atributos.
7. **Grupos**: sindicalizado y de confianza. Los aumentos de cada grupo se capturan por separado; por defecto
   confianza copia lo de sindicalizado.
8. **Supuestos de largo plazo con valores por defecto editables** (inflación, rotación, crecimiento de plantilla,
   revisiones futuras), cada uno con su fuente y fecha.
9. **Reporte BI completo**: corto plazo (12 meses), largo plazo (3 a 5 años) y pasivo laboral.
10. **Pasivo laboral, primera versión**: estimación simple del efecto en la **prima de antigüedad** con la fórmula
    legal; planes de jubilación como dato que captura el usuario; con aviso de que no sustituye una valuación
    actuarial (NIF D-3).
11. Un solo PR para todo el trabajo, con commits atómicos por comportamiento.
12. **Casos excepcionales de sueldo**: casi todo el mundo cobra el salario de su categoría y nivel, pero puede haber
    personas que cobran distinto (por encima, por debajo o con un suplemento personal). Deben ser pocas, y la
    herramienta tiene que poder manejarlas ("Sueldo individual", abajo).

## Objetivos

1. Una cuenta (empresa o sindicato) captura o importa su **tabulador** (categorías y niveles con su salario diario y
   vigencia) y liga a cada persona del padrón con su categoría y nivel.
2. Captura los **términos vigentes del contrato**: el catálogo de prestaciones con sus parámetros (días, porcentajes,
   montos, quién las recibe).
3. Arma **escenarios**: cambios al salario por grupo y a cualquier prestación (incluidas las a la medida), con fecha de
   vigencia, retroactividad y horizonte.
4. Ve y comparte un **reporte** con: costo del primer año (con retroactividad) y costo recurrente; cascada por
   concepto (salario, cada prestación, IMSS, Infonavit, impuesto sobre nómina); cortes por área, categoría y grupo;
   mes a mes del primer año; proyección a 3 a 5 años; efecto en la prima de antigüedad; costo del punto porcentual;
   sensibilidad de ±1 punto; comparación entre escenarios; y la tabla de supuestos con su vigencia.
5. Para el sindicato, el reporte muestra además el **valor bruto para la persona contra el costo para la empresa** de
   cada concepto, para decidir en qué insistir.

## No objetivos

- No es nómina: no timbra, no calcula recibos, no se integra con sistemas de nómina.
- No es una valuación actuarial ni asesoría legal o fiscal; el reporte lo dice.
- No modela el ISR de la persona. El valor para el trabajador es **bruto**; cada prestación marca si está exenta, y
  solo se señala. (Techo conocido; se mejora si se pide.)
- No gestiona el texto de las cláusulas del contrato.
- No hay un espacio compartido entre empresa y sindicato: cada uno hace su ejercicio y comparte el reporte.
- Nada de esto toca las encuestas ni los resultados de NOM-035, NOM-037 o Ley Silla.

## Anti-objetivos (fracasaría aunque funcione)

- Mostrar una cifra sin sus supuestos o con tasas fiscales vencidas sin avisar.
- Filtrar sueldos a quien no tenga permiso, a otra cuenta, a los logs o a los resultados de encuestas.
- Que una cuenta de sindicato vea algo de NOM-035, NOM-037 o Ley Silla, o que una cuenta sin el permiso vea dinero.
- Que la empresa tenga que cambiar cómo carga su padrón hoy: el comportamiento para empresas no empeora.

## Tipos de cuenta

`tenants` gana un tipo, `company` (todas las existentes) o `union`. Las cuentas de sindicato:

- Se dan de alta desde la consola de demos, como el resto (no hay registro libre).
- Usan los mismos datos de cuenta (razón social, nombre comercial, RFC, URL).
- Ven solo **Padrón** (empleados, categorías y niveles) y **Compensación**, más sus usuarios. No tienen centros de
  trabajo, campañas, NOM-037 ni Ley Silla.
- Las cuentas de empresa ven todo lo que ven hoy y, además, Compensación si tienen el permiso.

La forma de ocultar los módulos según el tipo (middleware, navegación y rutas) la decide el constructor, con un test
que pruebe que una cuenta de sindicato recibe 404 en las rutas de las herramientas de cumplimiento.

## Padrón (módulo `Organization`)

### Relajar el modelo (decisión del usuario)

Hoy `employees` exige contacto (`employees_contact_present`), género, centro de trabajo, área y nombre porque sirven a
las encuestas. Una cuenta de sindicato recibe de la empresa un archivo que puede no traer nada de eso.

- **Se relajan en la base de datos**: `email`/`mobile`, `gender`, `work_center_id`, `area_id` y `name` pasan a aceptar
  nulos. Se quitan `employees_contact_present` y el `NOT NULL` de las demás.
- **Identificación mínima**: un `CHECK` exige `name` o `employee_number`. Si falta el nombre, la persona se muestra por
  su número de empleado.
- **El comportamiento para empresas no cambia**: el formulario, el importador y las acciones de las cuentas de
  empresa siguen exigiendo contacto, género, centro de trabajo y área. Lo que exige cada herramienta se valida donde
  esa herramienta lo usa (p. ej. lanzar una campaña falla con la lista de personas sin contacto o sin centro).
- **Tests que cambian por esta decisión del usuario**: los de `tests/Feature/Organization/EmployeeStorageTest.php` que
  afirman que la base rechaza una persona sin contacto, género, centro, área o nombre (`employees_contact_present`,
  etc.). Se reemplazan por los de la nueva restricción de identificación mínima y por pruebas de que el formulario y el
  importador de cuentas de empresa siguen rechazándolos. Es el único cambio de comportamiento esperado.
- Esto cede integridad en la base para las cuentas de empresa y la compensa la validación de aplicación, con pruebas.
  (Decisión mía de diseño; ver "Decisiones del diseñador".)

### Dos identificadores

- `id` (UUID v4, ya existe): interno nuestro. Nunca aparece en un reporte que sale de la plataforma.
- `employee_number` (ya existe, único por cuenta sin distinguir mayúsculas): el número de la empresa. En las cuentas
  de sindicato es **obligatorio**; en las de empresa sigue opcional. En la UI se llama **ID de la empresa**.
  Todo reporte o exportación por persona usa este número.

### Estructura nueva en el padrón (sin dinero)

- `job_grades`: categoría y nivel del tabulador (`category`, `level`), de la cuenta, únicos por
  (categoría, nivel) sin distinguir mayúsculas. **No lleva salario.**
- En `employees`: `job_grade_id` (opcional), `hired_on` (fecha de ingreso, `date`) y `staff_group`
  (`unionized` | `trusted`, opcional hasta que se use Compensación).
- El importador de empleados admite las columnas opcionales `category`, `level`, `hired_on` y `staff_group`; una
  categoría o nivel nuevo se crea en el momento, como ya ocurre con las áreas.

## Compensación (módulo nuevo, `Compensation`)

Lee el padrón **solo por un contrato nuevo en `Organization`** (`Contracts/` + `Data/`), que entrega: número de la
empresa, categoría y nivel, área, grupo y fecha de ingreso; nunca nombre, contacto ni género. Para los cálculos
agrupados entrega **conteos por (categoría y nivel, área, grupo, años de servicio)**, de modo que el cálculo no
hidrata personas. Las pocas personas con sueldo individual se piden aparte, por su ID de la empresa (ver
`salary_overrides`).

### Datos

- `grade_salaries`: salario diario por categoría y nivel con `effective_from` (`date`); permite la historia y fija el
  año base. Referencia a `job_grades`.
- `benefits`: prestaciones de la cuenta. Las del catálogo base se siembran; las a la medida las crea el usuario. Cada
  una se define por atributos, de modo que **una prestación a la medida es un formulario, no código**:
  - cálculo: días de salario, % del salario, monto fijo por periodo o múltiplo de la UMA;
  - monto o valor y frecuencia (diaria, mensual, anual);
  - a quién aplica: grupo, categorías, antigüedad mínima; con tablas por antigüedad (p. ej. vacaciones);
  - si integra el salario base de cotización (siempre, nunca, o hasta un tope), si entra a la base del impuesto sobre
    nómina y de la PTU, si cuenta para la base de la prima de antigüedad, y si está exenta de ISR (solo se señala);
  - un tope opcional.
- `contract_terms`: los parámetros vigentes de cada prestación (el "año base").
- `salary_overrides` (**sueldo individual**): para la excepción. Una fila por persona con su **salario diario real**
  (no una diferencia), `effective_from` y una nota. Referencia al empleado. El dinero sigue fuera del padrón.
  - Se capturan en la pantalla de la persona dentro de Compensación o se importan con un CSV propio de Compensación
    (columnas: ID de la empresa y salario diario); el importador de empleados no cambia.
  - Quien tiene un sueldo individual se calcula con él en lugar del salario de su categoría y nivel. Un aumento
    porcentual del escenario se aplica igual a su sueldo individual, que es lo normal. Quien no tiene uno se calcula
    por el tabulador.
  - Las personas con sueldo individual **salen de los conteos agrupados** y se piden a `Organization` aparte, por su ID
    de la empresa; así los conteos siguen sin hidratar personas y lo individual se limita a unas pocas filas.
  - El reporte siempre muestra cuántas personas y qué porcentaje de la plantilla tienen sueldo individual, y las marca
    en el anexo por persona. No hay un tope, pero si pasan de la décima parte de la plantilla el reporte avisa que el
    tabulador probablemente no describe la realidad.
  - Siguen en pie la bitácora al ver o exportar, la redacción en logs y el permiso `manage-compensation`.
- `fiscal_parameters` (de la plataforma, con vigencia y fuente): UMA, salario mínimo, tope del SBC (25 UMA), tasas
  del IMSS y del Infonavit, mínimos de ley (aguinaldo, prima vacacional, tabla de vacaciones, prima de antigüedad con su
  tope), y el impuesto sobre nómina **por estado**. Las mantiene Humetria; una cuenta no las edita. Cada valor
  guarda su fuente y fecha, y el reporte avisa si usa un valor vencido.
- `assumptions`: valores por defecto editables por escenario (inflación, rotación, crecimiento de plantilla, revisiones
  futuras, tasa de los años siguientes), cada uno con fuente y fecha.
- `scenarios`: nombre, cambios por grupo (porcentaje al salario), cambios a prestaciones (nuevos términos),
  `effective_on`, retroactividad, horizonte (3 a 5 años) y supuestos. Se guardan y se comparan entre sí.

### Cálculo

Un servicio puro, determinista y sin HTTP (en `Compensation/Support`, como el de `Instruments`), que dados el padrón
agrupado, el tabulador, los términos, los parámetros fiscales y un escenario devuelve el costo desglosado. Reglas:

- El **factor de integración** es `1 + aguinaldo/365 + vacaciones × prima vacacional / 365` con los valores reales de
  cada persona (por antigüedad), no los mínimos de ley.
- Las cuotas y aportaciones se calculan sobre el SBC con su tope; las prestaciones respetan sus atributos de
  integración (p. ej. vales de despensa hasta el 40 % de la UMA diaria; fondo de ahorro solo si cumple los requisitos).
- **Dinero**: sin flotantes. Centavos enteros o `brick/math` si ya viene con el framework; **ninguna dependencia nueva
  sin aprobación**.
- La proyección a varios años aplica los supuestos y deja ver cada año; la antigüedad sube sola (más vacaciones, más
  prima). Las tasas patronales que cambian por año (la de cesantía y vejez sube gradualmente por la reforma de
  pensiones de 2020, **por verificar**) viven en `fiscal_parameters` con su vigencia.
- La estimación de la prima de antigüedad es `12 días × años de servicio × salario diario (con tope de dos salarios
  mínimos)` antes y después del escenario, solo para quien cumple las condiciones de ley, y se rotula como estimación.

### Reporte

Una página imprimible (mismo mecanismo que el reporte de Results: `print-button`) con las secciones del objetivo 4 y
un enlace compartible de solo lectura (como `shared-report`). **El reporte compartible agrupa en "Otros" los grupos
de menos del mínimo configurado (por defecto 5) y aplica supresión complementaria**: si "Otros" quedara también por
debajo del mínimo, se le suma el siguiente grupo más chico, para que restar los subtotales del total no deje
reconstruir un grupo oculto. Los totales siempre son exactos. El enlace compartible **no** incluye el anexo por
persona ni el desglose mensual por grupo (solo el mensual total). La vista interna muestra todo. Un anexo opcional
**por persona** (solo quien administra, nunca en el enlace) identifica con el ID de la empresa y nunca con el nuestro.
Toda exportación va a la bitácora.

## Permisos, privacidad y registro

- Permisos nuevos `view-compensation` y `manage-compensation`, mapeados desde los roles: dueño y RH los tienen;
  consulta y Comisión, no. Se exponen en `ResolveTenant` como los demás.
- Las acciones que cambian estado escriben log (`compensation.scenario_saved`, `compensation.tabulator_imported`, …).
  Ver y exportar un reporte, y exportar el anexo por persona, además van a la bitácora con `RecordAudit`.
- `RedactSensitiveData` incorpora las llaves de salario con su test.
- Ninguna página fuera de Compensación recibe salarios en sus props (test). Las pantallas del padrón muestran la
  categoría y el nivel, nunca el dinero.
- El salario, la categoría y el nivel **no se agregan** como filtros de los resultados de las encuestas (reducirían
  grupos y arriesgan reidentificar). Lo afirma un test.
- Cada modelo nuevo con `tenant_id` aplica el scope de cuenta y trae su test de aislamiento (la cuenta A no lee ni
  modifica lo de B; por URL, 404).
- Durante una suplantación de superadmin, Compensación no se puede ver (decisión mía).

## Interfaz

Pantallas nuevas, partiendo de las más parecidas de `docs/design-system/pantallas/` y revisadas en claro, oscuro y
375 px: tabulador (lista paginada por categoría y nivel), catálogo y términos de prestaciones, lista de escenarios,
editor de escenario y reporte. Toda lista es paginada en el servidor. Los textos van en español (es-MX) y tutean.
Si el reporte necesita una librería de gráficas que el proyecto no tiene, se pide aprobación; si no, se dibuja con
lo que ya hay.

## Pruebas

- **Cálculo** (Pest Unit, con `mutates()` en las clases de lógica): casos trabajados — factor de integración 1.0493
  con los mínimos de ley; ejemplo de $500 diarios con 15 días de aguinaldo, 12 de vacaciones y 25 % de prima →
  SBC $524.65; prima de antigüedad $120,000 a $126,000 con +5 % y 20 años de servicio; vales de despensa en el tope y
  un peso arriba; el tope de 25 UMA; el tope de la prima de antigüedad. Bordes: plantilla vacía, un grupo de una
  persona, categoría sin salario, sin vigencia, escenario sin cambios (costo cero), retroactividad cero, una persona con sueldo individual por encima y otra por
  debajo del tabulador (el aumento porcentual se aplica a su sueldo), un sueldo individual cuyo ID de la empresa no
  existe, uno que reemplaza a otro con distinta vigencia, y el aviso cuando pasan de la décima parte de la plantilla.
- **Feature**: aislamiento entre cuentas, permisos por rol, 404 para sindicato en herramientas de cumplimiento, props
  sin salarios fuera de Compensación, paginación, importador (columnas nuevas, número de empleado único), tests de log
  y bitácora.
- **Navegador**: `visitPage()` de cada página nueva y el flujo principal (importar tabulador, armar un escenario,
  ver y compartir el reporte).
- Las tasas y reglas fiscales se contrastan con **fuentes oficiales** (LSS, su reglamento, tablas del IMSS, UMA del
  INEGI); la investigación inicial usó fuentes secundarias y no las valida.

## Queda al constructor

Cómo se oculta cada herramienta según el tipo de cuenta; el nombre exacto de contratos, DTOs y tablas; la forma del
formulario de prestaciones a la medida; cómo se guarda y versiona un escenario; la distribución de pantallas; y las
tasas exactas vigentes, que se toman de fuentes oficiales.

## Decisiones del diseñador (mías, para que las revises)

1. Relajar las restricciones en la base y validar en aplicación para empresas, en lugar de dejar la base estricta y
   crear a los de sindicato con valores de relleno.
2. `job_grades` vive en `Organization` y el dinero en `Compensation` (la llave foránea hacia otro módulo ya tiene
   precedente: `employees` hacia `work_centers`).
3. Los parámetros fiscales los mantiene Humetria; la cuenta solo edita supuestos.
4. Los grupos pequeños se agrupan en "Otros" en el reporte compartible.
5. Compensación no se ve durante una suplantación.
6. El valor para el trabajador es bruto y no modela ISR.
7. Llamada "ID de la empresa" en la UI a `employee_number`.

### Resueltas tras la revisión del documento (también decisiones mías)

8. **Estado del impuesto sobre nómina.** Es una dimensión más de los conteos agrupados. Sale del estado del centro de
   trabajo de la persona; si no tiene centro (cuentas de sindicato), de una columna opcional `state` del importador
   (misma lista de estados que los centros); y si tampoco la trae, de un estado por defecto que se elige al armar el
   escenario. Lo que siga sin estado queda fuera de esa partida y el reporte lo señala.
9. **Prima de riesgo de trabajo del IMSS.** Es un dato por cuenta, con vigencia, dentro de los términos del contrato
   (un solo valor por cuenta y periodo). Sin él, la línea de riesgo de trabajo no se calcula y el reporte lo marca.
10. **Padrón incompleto.** El cálculo no se bloquea. Quien no tenga categoría y nivel o fecha de ingreso, o esté
    inactivo, queda fuera y el reporte abre con "X de Y personas costeadas", con un aviso y la lista de lo que falta
    (con el ID de la empresa).
11. **Parámetros fiscales.** En esta entrega son datos sembrados por migración o seeder, con su fuente y fecha, tomados
    de documentos oficiales y versionados en el repositorio. No hay pantalla para editarlos. Más allá de la última
    vigencia conocida se arrastra el último valor con un aviso visible en el reporte. El año base es el de la fecha
    de vigencia del escenario; los ejemplos de los tests usan los valores de 2026.
12. **Roles en una cuenta de sindicato.** Solo dueño y RH (la Comisión es de NOM-037 y consulta no ve dinero). El tipo
    de cuenta no cambia después de crearla; la consola de demos elige el tipo al crear la cuenta y la solicitud
    pública de demo no cambia. Una persona puede tener membresía en una cuenta de empresa y en una de sindicato (ya
    lo permite el modelo); no se le trata distinto.
13. **Orden respecto a Ley Silla.** El plan de implementación se escribe cuando Ley Silla ya esté en `main`. La regla
    de ocultar herramientas a las cuentas de sindicato se define como "toda herramienta de cumplimiento" sobre un
    registro y no como una lista fija, para que Ley Silla y las que vengan queden incluidas.

14. **Sueldo individual** (respuesta del usuario: existe, es poco frecuente y debe poder manejarse). Resuelto con
    `salary_overrides` en `Compensation`, sin dinero en el padrón. Mis llamadas: se guarda el salario diario real y
    no una diferencia; el aumento porcentual del escenario también se aplica a ese sueldo; la importación es un CSV
    propio de Compensación por ID de la empresa; sin tope, pero con aviso al pasar de la décima parte de la plantilla.

## Pendiente

Nada. Queda la aprobación del usuario.
