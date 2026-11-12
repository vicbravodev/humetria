# Estado actual (antes) de Organigrama, Vacaciones, Clima y Pulso — defectos verificados

Fuente: capturas en `scratchpad/before/` (demo-nom035, 212 empleados, datos sembrados). Marcado: [V] = lo vi yo en la captura; [A] = lo reportó el agente de captura, sin verificar a ojo.
Novedad en main (#93): el sidebar ahora agrupa por familias — Cumple la norma (NOM-035, NOM-037, Ley Silla, Asistencia), Escucha a tu gente (Quejas, Clima, Pulso), Ordena tu operación (Vacaciones, Organigrama, Compensación). Las migas ahora llevan la familia (p. ej. "Ordena tu operación › Organigrama"). Las capturas viejas muestran el sidebar anterior ("Cumplimiento/Herramientas").

## Transversales (las 4 herramientas)
- Acciones de solo texto repetidas por fila ("Ver", "Editar", "Expediente", "Cambiar", "Archivar"). [V/A]
- Pestañas con conteo 0 y estados vacíos sin siguiente paso. [V]
- Pestañas de 38 px a 375 px (mínimo 44). Objetivos táctiles < 44 px: saldos 56, solicitudes 12, compromisos clima 19. [A]
- Listas ordenadas por nombre en vez de por urgencia (calendario, saldos). [A]
- Porcentajes sin espacio ("30%") contra el estándar "30 %". [A]
- Más de 3 KPIs; acción primaria repetida; encabezados sin estado ni ficha con íconos. [V]

## Organigrama
- Índice: 3 KPI poco accionables ("Niveles 4", "Mediana 4.0"); NO hay árbol: 3 tarjetas de raíces en columna de 580 px y media pantalla vacía; 4 controles en fila + Imprimir; la tarjeta solo tiene ">" . [V]
- Calidad de datos: tabla con "Elige a su jefe directo" y un "Guardar" gris por fila; pestañas con conteo 0. [A]

## Vacaciones
- Resumen: 4 KPI (máx 3); franja arena de aviso + línea suelta de "198 constancias sin acuse" (dos avisos); "4340" pesa igual que "0"; pestañas "Fuera hoy 0" con vacío sin siguiente paso. [V]
- Solicitudes: columna Días dice "Tenía días · del periodo 2026" sin número; "Registrar a nombre de alguien" + "Ver" ×N; escalada vs pendiente solo difiere en subtexto gris; 6 pestañas con conteo. [A]
- Calendario: orden alfabético, 1 de 25 filas con barra, 9 páginas; cuadrícula cortada a la derecha; a 375 px solo 5 días; una sola barra gris sin color por tipo. [A]
- Empleado: KPI de texto ("Política: Personal de confianza"); movimientos/solicitudes sin pestañas; "Ajustar" solo texto. [A]
- Políticas: tarjetas sin tramos; "Personal de confianza" es etiqueta de fila y nombre de política; "Asignada a 1 grupo" sin # personas; 3 acciones primarias a la vista. [A]
- Editar/nueva política: 10 filas largas con borde, sin barra de guardado fija (Guardar a 2300 px), sin vista previa de días por antigüedad. [A]
- Configuración: pestañas con conteo; "De la ley"/"De la empresa" iguales; duplicado Paternidad. [A]
- Saldos: "Expediente" repetido en 25 filas; "Disponibles" solo texto sin medidor; orden por nombre; selección sin barra masiva. [A]
- Importar: asistente de 3 pasos con 900 px vacíos; "Revisar archivo" verde sin archivo. [A]
- Estado de cuenta: "30% sobre"; bloque "Sin acuse" separado del documento. [A]

## Clima
- Índice: tarjeta activa de 640 px y canvas vacío; historial en tabla con "Ver" de texto; "Ver avance →" en contorno siendo el principal. [A]
- Detalle: "Cerrar encuesta" y "Recordar a 118" juntos; el arco muestra "94" (conteo) sobre escala de 44 %; "Participación por grupo" con botones, no pestañas; sin lista de pendientes. [A]
- Resultados (2450 px): arco 79 % de participación junto a "62 % favorables" (dos porcentajes); "-27" sin explicación (la barra SÍ tiene leyenda de colores — corregido); "Qué mueve el compromiso" = 10 barras iguales sin jerarquía; "Por grupo" al fondo. [V]
- Por pregunta (4078 px): barras apiladas sin leyenda; sin filtros ni cortes. [A]
- Comentarios: filas de texto sin tema ni orden; ícono de chispas como segundo acento visual. [A]
- Compromisos: hasta 4 acciones de texto por tarjeta; "Nuevo compromiso" y "Ver resultados" juntos; "Descartado 0" sin vacío. [A]
- Jefes: tabla de 1 columna con "Ver retroalimentación"; no muestra participación ni estado; nota "Algunos jefes no aparecen" sin decir cuántos. [A]

## Pulso
- Índice: abre con estado vacío grande "Todavía no hay un pulso" aunque hay 3 en el historial; tres tarjetas de radio + "Crear un pulso" a ancho completo; "Ver tendencia" pesa igual; historial en tabla de "Ver". [A]
- Tendencia (2780 px): barras no empiezan en cero (62 % y 71 % casi iguales; recomendación −27…−13 plana); "Subió/Bajó" sin magnitud; 9 tarjetas "Por tema" con 2–3 barras del mismo jade, sin línea de referencia. [V]
- Resultados/jefes/compromisos: repite el patrón de Clima; "Sin respuestas suficientes" en 3 de 5 temas sin decir cuántas faltan; "Mejoras vistas 71 %" con frase en minúscula. [A]

## No capturado / no verificado
- Estados de primera vez reales (demo-sindicato no tiene acceso a estas herramientas).
- Oscuro: casi todo sin revisar a ojo. 375 px: solo calendario y organigrama vistos. Portales: capturados (200) sin revisar a ojo.
- Resúmenes de IA en pulsos/clima activo y por jefe: no existen en los datos sembrados.

## Datos de ejemplo disponibles (para que el "después" use cifras reales)
- Organigrama: 3 raíces sin jefe, 4 niveles (3, 9, 36, 164 personas), un jefe con 17 directos; raíces: Esther Alaniz Segundo (3 directos, 78 en total), Gonzalo Reyes Segundo (3, 66), Yeray Pulido (3, 65). 212 activas, 209 con jefe.
- Vacaciones: 198 periodos, 4340 días por disfrutar, 133 ventanas por cerrar, 7 por responder (4 escaladas), 0 fuera hoy; 30 solicitudes: 11 solicitadas (4 escaladas), 8 aprobadas, 6 disfrutadas, 4 rechazadas, 1 cancelada; políticas: Personal de confianza (default), Contrato colectivo, Política 2024 (archivada); 3 personas sin fecha de ingreso.
- Clima jun 2026 (cerrado): 168 de 212 (79 %), compromiso favorable 62 %, recomendación −27 (46 % no, 35 % indiferentes, 19 % sí), 3 compromisos. Clima nov 2026 (activo): 94 de 212 (44 %).
- Pulso jul/ago/sep 2026: 151, 139, 157 respuestas; compromiso 62→69→72→71 % (jun clima incl.), recomendación −27, −19, −21, −13; mejoras vistas 60, 73, 71 %. Programado mensual el día 15.
