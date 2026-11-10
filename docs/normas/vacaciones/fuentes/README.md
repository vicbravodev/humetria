# Fuentes oficiales de vacaciones

Copias de las fuentes en que se basan los [criterios](../criterios.md), descargadas el **2026-11-10**. Se guardan porque
las páginas del gobierno cambian o desaparecen: si eso pasa, aquí queda lo que decían cuando se tomaron las decisiones.

| Archivo | Qué es | Origen |
|---|---|---|
| `lft-vacaciones-extracto.txt` | LFT vigente (última reforma DOF 14-05-2026): arts. 56, 74, 76 a 81, 132 fr. XXVII Bis, 170, 170 Bis, 179, 516, 784, 804 y 805. Texto extraído del PDF de forma automática (por eso algunos artículos traen el guion en la línea siguiente); el PDF manda. | <https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf> |
| `dof-2022-12-27-decreto-vacaciones.txt` | Decreto por el que se reforman los artículos 76 y 78 de la LFT, en materia de vacaciones, con sus dos transitorios. Texto extraído del HTML oficial, sin el marco de la página. | <https://dof.gob.mx/nota_detalle.php?codigo=5675889&fecha=27/12/2022> (HTML original: SHA-256 `db2139002d61d159bb1edb04b8755f56410e39a0fa66dfb663824f24b1ee651b`) |
| `lft-2022-arts-76-78-texto-anterior.txt` | Arts. 76 y 78 de la LFT como estuvieron vigentes hasta el 31-12-2022 (compilación con última reforma DOF 18-05-2022). | Copia del PDF oficial de la Cámara de Diputados guardada por Internet Archive el 03-11-2022: <https://web.archive.org/web/20221103052859/https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf> |

Copias que no se guardaron en el repositorio, por tamaño, y que se pueden volver a bajar con su huella:

| Documento | SHA-256 |
|---|---|
| LFT.pdf (Cámara de Diputados, reforma 14-05-2026, 457 páginas) | `12f09393a1951a91c3f57f579bf611b034edf1a5f78cdbfb828e23ff3a9acbf7` |
| LFT.pdf (Cámara de Diputados, reforma 18-05-2022, 330 páginas, vía Internet Archive) | `5776ff204d8d4dae23495a0f44b3cbf52673d9e56d2b6d5bc64d5ebab79fb5e3` |

## Verificaciones hechas el 2026-11-10

- **Tabla del art. 76.** El texto vigente y el del decreto dicen lo mismo, palabra por palabra. Los arts. 76 y 78 solo
  traen la anotación «Artículo reformado DOF 27-12-2022»: ninguna reforma posterior los toca (incluida la de jornada
  del 01-05-2026).
- **Tabla anterior.** Confirmada en la compilación de 2022: mínimo de 6 días, 2 más por año hasta 12, y «después del
  cuarto año» 2 más por cada cinco. El art. 78 anterior pedía 6 días continuos.
- **El decreto no conserva la tabla anterior en un transitorio.** Solo tiene dos: Primero (vigencia desde el
  1 de enero de 2023) y Segundo (aplica a los contratos vigentes cuando sea más favorable). El diseño suponía un
  transitorio que no existe; el criterio C-02 explica de dónde sale la regla.
- **Lista del art. 74.** Nueve fracciones; la VII (1 de octubre de cada seis años) fue reformada en el DOF del
  30-09-2024.
- **Licencias.** Paternidad (132 XXVII Bis), maternidad y adopción (170 II y II Bis) y cuidados por cáncer (170 Bis)
  están en el texto vigente. **No hay licencia por luto**: se buscaron «luto», «duelo» y «fallec» en todo el texto; las
  coincidencias son de otros temas (beneficiarios del trabajador fallecido, prisión «absolutoria»).
- **Prescripción y prueba.** Arts. 516, 784 (fracciones I, II, X y XI), 804 (fracción IV y párrafo final) y 805,
  copiados del texto vigente.

## Lo que no se pudo cotejar

| Qué | Qué pasó | Dónde quedó |
|---|---|---|
| Jurisprudencia 2a./J. 1/97 (prescripción de vacaciones y prima vacacional), Segunda Sala de la SCJN | El Semanario Judicial de la Federación (<https://sjf2.scjn.gob.mx>) responde a una consulta automática con una página de verificación, no con la tesis. Fuentes secundarias le dan el registro digital 199519; no está verificado. | C-11 queda como «Ley + Nuestro»: la cuenta de los 18 meses se sostiene en los arts. 81 y 516. Pendiente: abrir la tesis a mano y, si coincide, citarla con su registro. |
| Preguntas frecuentes de la STPS sobre la reforma de 2023 | No se localizó una copia oficial descargable (la dirección de una copia estatal devolvió una página, no el documento). | C-01 y C-02 se sostienen en el texto de la ley. |
| Tesis sobre festivos o incapacidad dentro del periodo de vacaciones | No se buscó en el Semanario por la misma razón. | C-04 se sostiene en el texto («días laborables», art. 74); C-05 es «Nuestro». |

## Huellas SHA-256 de los archivos guardados

| Archivo | SHA-256 |
|---|---|
| `lft-vacaciones-extracto.txt` | `451cfbb17ed9985da8e6f0ba0a510b6b0cfac9e5d7b2727e3910bbda3d520997` |
| `dof-2022-12-27-decreto-vacaciones.txt` | `0459e2f06c9b5c0f7e075d2260efd2064668c2de1c3618a4085c5aa096e972e1` |
| `lft-2022-arts-76-78-texto-anterior.txt` | `2f9894699c3f44264ffb3812ab598ea2b3f28b147515aa451c2e838ce13c00ff` |
