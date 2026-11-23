# Valores esperados (solo para el formador)

Generado por `tools/generate_seed.py`. Sirve para comprobar las vistas, las medidas y el
informe. Fecha de corte de los datos: 31/10/2026. Solo empresa `CONSTRUCTORA DEMO`.

Definiciones usadas aquí (el proposal de la demo puede definirlas de otra forma;
si lo hace, las cifras cambian y hay que razonar por qué):

- **Presupuesto coste**: suma de `Total Cost (LCY)` de `Job Planning Line` (todos los tipos de línea).
- **Presupuesto venta**: suma de `Line Amount (LCY)` de `Job Planning Line` con `Line Type` 1 o 2.
- **Coste real**: suma de `Total Cost (LCY)` de `Job Ledger Entry` con `Entry Type` = 0 (consumo).
- **Certificado**: suma de `Line Amount (LCY)` de `Job Ledger Entry` con `Entry Type` = 1, cambiada de signo.
- **Producción**: por capítulo, último `Completion _` de `Job Progress Entry` × presupuesto de venta
  del capítulo (sin la línea sin fecha).
- **Avance planificado a la fecha de corte**: presupuesto de venta con `Planning Date` ≤ corte
  (sin la línea sin fecha) / presupuesto de venta.
- **Hitos retrasados**: cumplidos después de la fecha planificada, o no cumplidos con la fecha
  planificada ya pasada.

## Económico por obra

| Obra | Estado | Ppto. coste | Ppto. venta | Coste real | Certificado | Producción | Pte. certificar | Margen real (cert. − coste) |
|---|---|---:|---:|---:|---:|---:|---:|---:|
| OB-25001 | Terminada | 6.052.000,00 | 6.800.000,00 | 6.081.367,59 | 6.800.000,00 | 6.800.000,00 | 0,00 | 718.632,41 |
| OB-25002 | Terminada | 2.208.000,00 | 2.400.000,00 | 2.525.524,14 | 2.400.000,00 | 2.400.000,00 | 0,00 | -125.524,14 |
| OB-25003 | Abierta | 4.488.000,00 | 5.100.000,00 | 4.298.541,71 | 4.263.100,20 | 4.540.672,80 | 277.572,60 | -35.441,51 |
| OB-25004 | Terminada | 1.420.000,00 | 1.635.000,00 | 1.389.765,92 | 1.600.000,00 | 1.600.000,00 | 0,00 | 210.234,08 |
| OB-25005 | Abierta | 3.510.000,00 | 3.900.000,00 | 2.846.534,34 | 2.400.076,23 | 3.151.629,00 | 751.552,77 | -446.458,11 |
| OB-25006 | Abierta | 3.780.000,00 | 4.200.000,00 | 3.072.276,21 | 3.132.486,00 | 3.351.902,40 | 219.416,40 | 60.209,79 |
| OB-25007 | Terminada | 1.092.000,00 | 1.200.000,00 | 1.096.906,65 | 1.200.000,00 | 1.200.000,00 | 0,00 | 103.093,35 |
| OB-26001 | Abierta | 2.880.000,00 | 3.200.000,00 | 2.137.186,58 | 2.067.296,00 | 2.371.411,20 | 304.115,20 | -69.890,58 |
| OB-26002 | Abierta | 3.872.000,00 | 4.400.000,00 | 1.606.312,87 | 1.502.292,00 | 1.801.879,20 | 299.587,20 | -104.020,87 |
| OB-26003 | Planificación | 801.000,00 | 900.000,00 | 0,00 | 0,00 | 0,00 | 0,00 | 0,00 |
| **Total** | | **30.103.000,00** | **33.735.000,00** | **25.054.416,01** | **25.365.250,43** | **27.217.494,60** | **1.852.244,17** | **310.834,42** |

## Plazos y avance por obra

| Obra | Inicio | Fin planificado | Fin real | Avance planificado | Avance real (producción / ppto.) | Hitos retrasados | Máx. días de retraso |
|---|---|---|---|---:|---:|---:|---:|
| OB-25001 | 01/01/2025 | 31/08/2026 | 30/09/2026 | 100,0 % | 100,0 % | 2 | 23 |
| OB-25002 | 01/02/2025 | 31/01/2026 | 31/01/2026 | 100,0 % | 100,0 % | 1 | 8 |
| OB-25003 | 01/07/2025 | 31/01/2027 | — | 89,3 % | 89,0 % | 1 | 2 |
| OB-25004 | 01/04/2025 | 31/05/2026 | 31/05/2026 | 100,0 % | 100,0 % | 1 | 3 |
| OB-25005 | 01/09/2025 | 31/12/2026 | — | 81,7 % | 80,8 % | 1 | 2 |
| OB-25006 | 01/06/2025 | 30/09/2026 | — | 100,0 % | 79,8 % | 5 | 113 |
| OB-25007 | 01/09/2025 | 30/06/2026 | 30/06/2026 | 100,0 % | 100,0 % | 1 | 6 |
| OB-26001 | 01/01/2026 | 28/02/2027 | — | 74,3 % | 74,1 % | 1 | 9 |
| OB-26002 | 01/03/2026 | 31/08/2027 | — | 41,8 % | 41,0 % | 1 | 4 |
| OB-26003 | 01/12/2026 | 31/07/2027 | — | 0,0 % | 0,0 % | 0 | 0 |

## Coste por capítulo en las obras con sobrecoste

| Obra | Capítulo | Ppto. coste | Coste real | Desviación |
|---|---|---:|---:|---:|
| OB-25002 | 01 | 165.120,00 | 187.645,96 | 13,6 % |
| OB-25002 | 02 | 288.960,00 | 334.602,16 | 15,8 % |
| OB-25002 | 03 | 660.480,00 | 761.266,59 | 15,3 % |
| OB-25002 | 04 | 288.960,00 | 333.367,50 | 15,4 % |
| OB-25002 | 05 | 330.240,00 | 385.582,07 | 16,8 % |
| OB-25002 | 06 | 206.400,00 | 237.357,33 | 15,0 % |
| OB-25002 | 07 | 41.280,00 | 47.235,50 | 14,4 % |
| OB-25002 | 08 | 41.280,00 | 47.399,27 | 14,8 % |
| OB-25002 | 09 | 41.280,00 | 47.192,45 | 14,3 % |
| OB-25002 | 10 | 144.000,00 | 143.875,31 | -0,1 % |
| OB-25003 | 01 | 167.280,00 | 166.929,38 | -0,2 % |
| OB-25003 | 02 | 418.200,00 | 423.854,53 | 1,4 % |
| OB-25003 | 03 | 1.003.680,00 | 1.309.696,36 | 30,5 % |
| OB-25003 | 04 | 752.760,00 | 745.954,31 | -0,9 % |
| OB-25003 | 05 | 836.400,00 | 798.454,92 | -4,5 % |
| OB-25003 | 06 | 669.120,00 | 414.060,51 | -38,1 % |
| OB-25003 | 07 | 167.280,00 | 18.870,55 | -88,7 % |
| OB-25003 | 08 | 83.640,00 | 79.894,20 | -4,5 % |
| OB-25003 | 09 | 83.640,00 | 78.856,54 | -5,7 % |
| OB-25003 | 10 | 306.000,00 | 261.970,41 | -14,4 % |

## Recuento de filas

| Tabla | Filas |
|---|---:|
| `CONSTRUCTORA DEMO$Customer` | 8 |
| `CONSTRUCTORA DEMO$Resource` | 8 |
| `CONSTRUCTORA DEMO$Dimension Value` | 8 |
| `CONSTRUCTORA DEMO$Job` | 10 |
| `CONSTRUCTORA DEMO$Job Task` | 120 |
| `CONSTRUCTORA DEMO$Job Planning Line` | 789 |
| `CONSTRUCTORA DEMO$Job Ledger Entry` | 2777 |
| `CONSTRUCTORA DEMO$Job Progress Entry` | 514 |
| `CONSTRUCTORA DEMO$Job Milestone` | 50 |
