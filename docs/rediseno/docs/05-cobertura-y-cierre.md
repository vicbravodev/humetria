# Cobertura de pantallas y diseño de quejas, difusión y compensación

Lienzo: https://claude.ai/artifact/Mabe2mH9pPgE8FKA2BvY6Q, página **v2 · quejas, difusión y compensación** (pantallas 26–38). Complementa `02-rediseno-rutas-guiadas.md`.

## Cobertura de las capturas del 4 nov 2026 (00–20)
**Ya diseñado (sin cambios):** 00 público (login, contraseña, invitación, landing, enlace vencido) y 01 encuesta en el lienzo anterior · 02 portal NOM-037 (v1 que se queda + 25) · 10 panel (8), paleta y 404, Humi en su propio artifact · 11 organización · 12 NOM-035 (el más maduro; reporte, 5, 6, 12) · 15 NOM-037 (2, 3, 9, 11, 13, 15, 16, 19, 23 y v1 que se quedan) · 16 Ley Silla (4 y v1 que se quedan) · 19 cuenta · 20 consola.
**Faltaba y ya está en la página "v2 · quejas, difusión y compensación":**
- 26 Canal de quejas: a quién llega cada queja según contra quién es (nadie atiende una queja contra sí mismo o su rol), un siguiente paso cuando falta cobertura, enlace y QR, plazos, conteos con "menos de 5" y evidencia sin contenido de casos.
- 27 Casos por atender: agrupados por lo que toca (acuse, revisión), plazo visible y aviso diario por correo sin contenido.
- 28 Atender un caso: Recibida → Acuse → Revisión → Cerrada; acuse ya redactado; conversación con la persona separada de las notas internas; excusarse; conservar por proceso legal; bitácora.
- 29 Formulario de la persona (celular): 4 pasos en usted; referencia más clave elegida por la persona para el seguimiento anónimo (patrón de buzones como EQS Integrity Line).
- 30 Difusión: una matriz de 6 cosas que deben conocer × centros, en lugar de 6 pestañas; siguiente paso; página, cartel y confirmaciones.
- 31 Contenidos de 5.7 con preguntas y vista previa en celular.
- 32 Evidencia de difusión: confirmaciones por centro, firmas en papel, medios físicos en un minuto y publicar resultados (7.8) sin grupos de menos de 5.
- 33 Página de las personas por centro: la queja primero, política con "Confirmo que la leí", resultados en lenguaje llano.
- 34 Compensación: datos del contrato con su estado y escenarios con costo comparado; variantes de sindicato y primera vez.
- 35 Escenario con costo en vivo: el costo se recalcula al mover cada valor, comparado con los otros escenarios.
- 36 Reporte: un solo aviso de exactitud con acción, en lugar de 3 avisos sueltos; de dónde sale el costo; detalle plegado. Variante compartida por enlace: cubre 05; el enlace vencido es el estado 24.
- 37 Comparar: muestra la distancia a negociar entre las propuestas y el costo de cada punto y de cada mes de retroactivo.
- 38 Datos del contrato: tabulador con su problema en línea y una zona para arrastrar el CSV.

**Se quedan como están implementadas, con ajustes:** NOM-037 ajustes, capacitación, lista de verificación y comisión; Ley Silla asientos, reglamento, encuesta y documentos. Los ajustes son:
- entrar desde la etapa de su ruta;
- cambiar los campos de archivo nativos por la zona de arrastre;
- en listas sin datos, usar el estado vacío real en lugar de "Nadie coincide".

## Errores vistos en las capturas (para Claude Code)
1. `asistente-conversaciones` muestra JSON crudo en lugar de la página.
2. `elegir-empresa` muestra la lista de Empresas.
3. El cartel, la página de las personas y el canal muestran 127.0.0.1:8010. Deben usar el dominio de producción con enlace corto.
4. Hay "Choose File / No file chosen" en Documentos NOM-037, Ley Silla, plan y calculadora.
5. En quejas, "menos de 5" se rompe en tarjetas angostas. Hay que mostrarlo en lista.
6. En la calculadora, los avisos fiscales se repiten en cada reporte. Hay que juntarlos en un aviso de exactitud.

## A validar (compensación y quejas)
- Las fórmulas del costeo en vivo del prototipo 35 son lineales e ilustrativas. El motor real debe calcular por persona, con topes del IMSS.
- Las cifras de "cada punto cuesta": hay que confirmar cómo se calcula cada cuota con su tope.
- Los plazos de acuse y respuesta de quejas son de la empresa, no de la norma. Falta validar la conservación de 24 meses con un abogado.

## Cierre antes de desarrollo (4 nov 2026, segunda revisión)
- 39 Canalización NOM-035: registrar a dónde (IMSS, servicio médico, apoyo psicológico, otra, "No quiso atenderse"), cómo decírselo (en persona con guion, o aviso en su portal) y un recordatorio a 30 días. Sustituye a la pantalla v1. El Panel y la ruta ya llevan aquí. El Panel ahora dice 9 pendientes.
- 40 Menú por rol, el orden canónico: Panel, Centros, Empleados, Usuarios / Cumplimiento: NOM-035, NOM-037, Ley Silla, Quejas / Herramientas: Compensación. Ya está aplicado a los menús de los boards v2.
- 41 Correos de quejas (sin contenido), designación externa, reporte compartido, recordatorio de difusión y aviso de la política a la persona.
- 42 Carteles imprimibles tamaño carta: página de las personas y quejas.
- 43 La persona ve cómo va su queja con referencia y clave, y puede agregar mensajes. Incluye el estado de canal en pausa con la vía de la STPS.
- 44 Nueva prestación: tipo de pago, a quién le toca, tabla por antigüedad, si integra al salario de cotización, y su costo en vivo.
- 45 Designar a quien atiende, incluida una persona externa sin cuenta que entra con enlace personal.
- El Panel muestra las quejas por acusar en "Atiende primero", sin contenido.
- La matriz de roles (20) suma 3 filas: contenido de quejas, canal de quejas y compensación.

**Regla responsiva:** las filas de lista y tabla se acomodan con container queries, según el ancho de su tarjeta. La columna lateral baja a partir de 1240 px.

## Reporte de compensación con análisis (36, rehecho)
Sigue el mismo estándar que el informe de NOM-035: pestañas fijas, cifras que cuentan hacia arriba una sola vez, barras que crecen una sola vez (respeta reduced-motion), tooltip al pasar el cursor e impresión limpia. Todo va en un solo tono de jade con etiquetas; no hay paleta categórica.
Secciones:
- Resumen: 4 cifras y 3 hallazgos en lenguaje llano.
- De dónde sale el costo: cascada por concepto.
- Quién recibe el aumento: pesos al mes por categoría, sindicalizados contra confianza.
- Los próximos años: 3 años con sus supuestos.
- Frente a las otras propuestas.
- Anexo: detalle por concepto.

La proyección a 3 años es ilustrativa. Para cada año aplica la plantilla (+1.3 %) y las revisiones futuras (7.3 %) sobre el costo recurrente. El motor real debe definirla.
NOM-037 y Ley Silla se quedan como recopilación de datos, con su expediente. No llevan análisis.
