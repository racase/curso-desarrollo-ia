# Validación del modelo de plazos y avance

Fecha: 25/11/2026. Continuación autorizada mediante la petición «continua», después de finalizar la fase SQL. Se utilizó exclusivamente el MCP `powerbi-modeling` para crear tablas, relaciones, medidas, actualizar datos y ejecutar DAX. La conexión se estableció al proyecto `ObrasModel/Obras.pbip` abierto en Desktop, puerto 59961 durante esta sesión.

## Cambios persistidos

- Cuatro tablas importadas: `FactPlanificacion` (642 filas), `FactMedicionAvance` (514), `FactHito` (50) y `DimCorteDatos` (1).
- Particiones M sobre las vistas `rpt`, usando los parámetros existentes `Servidor` y `BaseDatos`. Sin credenciales en los ficheros.
- Nueve relaciones nuevas, sin modificar las ocho anteriores: seis relaciones de planificación y mediciones con obra, partida y calendario; una de hitos con obra; dos relaciones temporales de hitos inactivas.
- Diecisiete medidas nuevas en sus tablas de hechos, con descripciones de la spec, formatos españoles y carpeta «Plazos y avance». Definiciones reproducibles en `modelo_mcp.json`.

Las fechas de hitos no filtran activamente su lista: el calendario determina el corte consultado y las medidas clasifican todos los hitos de las obras seleccionadas. Así no desaparecen hitos planificados en otro mes al consultar un corte histórico. `DimCorteDatos` está desconectada y proporciona el corte global del ERP.

Tras crear relaciones se recalculó el modelo; una actualización completa posterior mediante MCP terminó sin errores. Se persistió el modelo con `database_operations.ExportToTmdlFolder` en `ObrasModel/Obras.SemanticModel/definition`, verificando la existencia de los cuatro TMDL nuevos y sus referencias. No se editó TMDL manualmente. La exportación incluye el identificador de base de la sesión, compatibilidad Power BI e idioma del motor; no cambia las fórmulas del issue 01.

## Conciliación de indicadores SQL/DAX

Referencia SQL independiente: `validacion_modelo.sql`, exclusivamente sobre tablas `dbo` de NAV. Consulta DAX ejecutada por MCP: `validacion_modelo.dax`.

Se compararon **624 filas**, correspondientes a **24 cortes × 26 grupos**. Los cortes incluyen los 22 cierres de enero de 2025 a octubre de 2026, el 01/01/2025 anterior a las primeras mediciones y el 15/10/2026 como corte dentro del mes. Los grupos incluyen total, diez obras, tres delegaciones, cinco tipos, cuatro jefes y tres estados.

Cada fila contiene trece indicadores: base elegible, venta prevista acumulada, producción, avance planificado y real, desviación en puntos, índice de plazo, venta sin fecha excluida, hitos retrasados, máximo de días pendientes, cumplidos tarde, pendientes y cumplidos a tiempo.

Resultado de `comparar_modelo.py`, guardado en `evidencias/comparacion_modelo.json`:

- Claves diferentes: **0**; indicadores discrepantes: **0**.
- Diferencia máxima en importes, recuentos y días: **0**.
- Diferencia máxima en ratios y puntos: **0,000000000050746346**, inferior a la tolerancia común de `0,00000001` debida a representación decimal/flotante.
- Se conservan los índices vacíos cuando el avance planificado es cero; no se convierten en cero.

En la referencia SQL, el índice se calcula como producción/venta prevista acumulada, algebraicamente equivalente al cociente de avances, conservando precisión suficiente para evitar truncamiento al dividir dos cocientes `decimal(38,…)`.

## Estados y días por hito

`validacion_hitos.dax` se ejecutó mediante MCP y se contrastó con la referencia NAV individual publicada por `validacion_hitos.sql`. `comparar_hitos.py` confirma **1.100 filas** (50 hitos × 22 cierres), con **cero diferencias** en estado y días, incluidos los 413 casos que aún no estaban cumplidos en el corte histórico aunque tengan fecha real posterior.

Al corte ERP 31/10/2026:

| Comprobación | Resultado DAX = NAV |
| --- | --- |
| Obra OB-25006 | Planificado 100 %; real 79,8072 %; desviación −20,1928 puntos |
| Hito OB-25006/HIT05 | Retrasado, 31 días; único pendiente vencido |
| Hitos cumplidos con retraso | 13; no incrementan el contador de pendientes vencidos |
| Obra OB-26003 | Avances cero, índice vacío y ningún hito retrasado |
| Revisión OB-25004/06 | 35.000 € excluidos; base elegible de obra 1.600.000 € |

## Regresión y evidencias

Las exportaciones MCP de `DimObra`, `DimPartida`, `DimFecha`, `FactPresupuesto`, `FactMovimiento` y `FactAvance` son idénticas antes y después. Evidencias completas: `evidencias/tablas01_antes.json` y `tablas01_despues.json`, ambas con SHA-256 `18254ed8d838a27f79b43e25a3bf912b262f740e72a6cdfae0babc043a47544d`. La comparación incluye columnas, particiones, medidas y calendario. Las relaciones nuevas filtran en un solo sentido hacia sus hechos y no alteran los recorridos existentes.

La consulta económica global antes y después conserva: presupuesto de venta 33.735.000 €, coste real 25.054.416,01 €, producción 27.217.494,60 € y certificado 25.365.250,43 €. Resumen en `evidencias/regresion_issue01.json`. Los cinco ficheros SQL del issue 01 siguen pasando `sha256sum -c` contra las huellas originales.

Salidas completas: `evidencias/modelo_sql.txt`, `modelo_dax.json`, `hitos_dax.json` y `validacion_hitos.txt`. No hay respuestas DAX truncadas.

## Estado

Al terminar esta fase estaban completadas 14 de 18 tareas. La continuación añadió las páginas y una medida auxiliar, con 18 medidas nuevas en total. La validación local y el archivo posterior están registrados en `validacion_informe.md`; la publicación permanece pendiente.
