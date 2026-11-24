# Prueba del modelo frente al ERP

Comparación de las medidas DAX del modelo `Obras` (Power BI Desktop, vía MCP) con
`sql/test/control_economico.sql`, que calcula los mismos indicadores directamente sobre las
tablas NAV de `CONSTRUCTORA DEMO`, sin usar las vistas `rpt`. Fecha de corte: 31/10/2026.
Ejecutada el 24/11/2026.

**Método.** La salida del script SQL se convierte con un script, sin transcribir a mano,
en una tabla `DATATABLE` de DAX. La consulta DAX calcula, para cada fila, la medida
filtrada por `TREATAS` sobre `DimObra` (y `DimCapitulo` en la 4.2) y la resta del valor SQL.
Los blancos de DAX en importes se toman como 0. Los porcentajes se comparan en puntos
porcentuales redondeados a dos decimales, igual que en el script SQL. Un blanco en ambos
lados cuenta como coincidencia.

## 4.1 Indicadores por obra

Valores (SQL; los de DAX son idénticos, ver diferencias):

| Obra | Ppto. coste | Ppto. venta | Coste real | Certificado | Producción | Coste previsto | Pte. certificar | Desviación | Desv. % | Margen previsto | Margen real | Margen real % |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| OB-25001 | 6.052.000,00 | 6.800.000,00 | 6.081.367,59 | 6.800.000,00 | 6.800.000,00 | 6.052.000,00 | 0,00 | 29.367,59 | 0,49 % | 748.000,00 | 718.632,41 | 10,57 % |
| OB-25002 | 2.208.000,00 | 2.400.000,00 | 2.525.524,14 | 2.400.000,00 | 2.400.000,00 | 2.208.000,00 | 0,00 | 317.524,14 | 14,38 % | 192.000,00 | −125.524,14 | −5,23 % |
| OB-25003 | 4.488.000,00 | 5.100.000,00 | 4.298.541,71 | 4.263.100,20 | 4.540.672,80 | 3.995.792,06 | 277.572,60 | 302.749,65 | 7,58 % | 612.000,00 | 242.131,09 | 5,33 % |
| OB-25004 | 1.420.000,00 | 1.635.000,00 | 1.389.765,92 | 1.600.000,00 | 1.635.000,00 | 1.420.000,00 | 35.000,00 | −30.234,08 | −2,13 % | 215.000,00 | 245.234,08 | 15,00 % |
| OB-25005 | 3.510.000,00 | 3.900.000,00 | 2.846.534,34 | 2.400.076,23 | 3.151.629,00 | 2.836.466,11 | 751.552,77 | 10.068,23 | 0,35 % | 390.000,00 | 305.094,66 | 9,68 % |
| OB-25006 | 3.780.000,00 | 4.200.000,00 | 3.072.276,21 | 3.132.486,00 | 3.351.902,40 | 3.016.712,16 | 219.416,40 | 55.564,05 | 1,84 % | 420.000,00 | 279.626,19 | 8,34 % |
| OB-25007 | 1.092.000,00 | 1.200.000,00 | 1.096.906,65 | 1.200.000,00 | 1.200.000,00 | 1.092.000,00 | 0,00 | 4.906,65 | 0,45 % | 108.000,00 | 103.093,35 | 8,59 % |
| OB-26001 | 2.880.000,00 | 3.200.000,00 | 2.137.186,58 | 2.067.296,00 | 2.371.411,20 | 2.134.270,08 | 304.115,20 | 2.916,50 | 0,14 % | 320.000,00 | 234.224,62 | 9,88 % |
| OB-26002 | 3.872.000,00 | 4.400.000,00 | 1.606.312,87 | 1.502.292,00 | 1.801.879,20 | 1.585.653,69 | 299.587,20 | 20.659,18 | 1,30 % | 528.000,00 | 195.566,33 | 10,85 % |
| OB-26003 | 801.000,00 | 900.000,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | — | 99.000,00 | 0,00 | — |

Diferencia DAX − SQL:

| Obra | Ppto. coste | Ppto. venta | Coste real | Certificado | Producción | Coste previsto | Pte. certificar | Desviación | Desv. % | Margen previsto | Margen real | Margen real % |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| OB-25001 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-25002 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-25003 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-25004 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-25005 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-25006 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-25007 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-26001 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-26002 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| OB-26003 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 | blanco = blanco | 0,00 | 0,00 | blanco = blanco |

**Resultado: diferencia 0,00 € en las 10 obras y los 12 indicadores.** `OB-26003` (sin
empezar) sale con presupuesto, sin coste, producción ni certificado, y con los porcentajes en
blanco, sin errores.

## 4.2 Obra × capítulo

Comparación de las 100 filas obra × capítulo del script SQL (resultado 3) con presupuesto
de coste, presupuesto de venta, coste real, producción, coste previsto, desviación de coste
y desviación %:

| Indicador | Filas | Diferencia máxima (valor absoluto) |
|---|---:|---:|
| Presupuesto de coste | 100 | 0,00 € |
| Presupuesto de venta | 100 | 0,00 € |
| Coste real | 100 | 0,00 € |
| Producción | 100 | 0,00 € |
| Coste previsto | 100 | 0,00 € |
| Desviación de coste | 100 | 0,00 € |
| Desviación de coste % | 100 | 0,00 puntos |

La desviación % está en blanco en 15 filas, que son los capítulos sin coste previsto, y lo
está en ambos lados: no hay ninguna fila en blanco en un lado y con valor en el otro.

Suma de capítulos frente a obra. Para cada obra, la suma por capítulo de cada medida
aditiva (`SUMX(VALUES(DimCapitulo[CapituloId]), [medida])`) menos la medida a nivel de obra:

| Medida | Obras | Diferencia máxima |
|---|---:|---:|
| Presupuesto de coste | 10 | 0,00 € |
| Presupuesto de venta | 10 | 0,00 € |
| Margen previsto | 10 | 0,00 € |
| Coste real | 10 | 0,00 € |
| Certificado | 10 | 0,00 € |
| Producción | 10 | 0,00 € |
| Coste previsto | 10 | 0,00 € |
| Pendiente de certificar | 10 | 0,00 € |
| Desviación de coste | 10 | 0,00 € |
| Margen real | 10 | 0,00 € |

**Resultado: la suma de capítulos es igual a la obra en todas las medidas aditivas, y la
desviación % de cada capítulo coincide con SQL.**

## Otras comprobaciones del modelo

- Coste real por año (`Calendario[Año]`) igual a SQL: 2025 = 11.170.008,83 €;
  2026 = 13.884.407,18 €.
- Capítulos con `Color desviación` en rojo: los mismos 14 que superan el 5 % en SQL. Ejemplo:
  `OB-25004`/`06`, con −7,17 %, no sale en rojo. `Color margen real` solo sale en rojo en
  `OB-25002`, la única obra con margen real negativo.
- `Fecha de corte` = 31/10/2026 en todas las obras, también en `OB-26003`, que no tiene
  movimientos.
