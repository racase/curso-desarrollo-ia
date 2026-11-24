# Tasks

Las tareas marcadas **(Persona)** se hacen a mano en Power BI Desktop; el resto las hace el
agente.

## 1. Cálculo de referencia sobre NAV

- [x] 1.1 Crear `sql/test/control_economico.sql`, que calcule por obra, directamente sobre las tablas NAV de `CONSTRUCTORA DEMO` y sin usar `rpt`: presupuesto de coste y de venta, coste real, certificado, producción, coste previsto, pendiente de certificar, desviación de coste y margen real, con las definiciones de la spec y el redondeo de `design.md`. Verificar que devuelve 10 obras, que `OB-26003` sale con presupuesto y coste 0, y enseñar el resultado
- [x] 1.2 Añadir al mismo script la comprobación de que cada código de capítulo tiene un solo nombre en todas las obras, y verificar que no devuelve filas

## 2. Vistas de reporting

- [x] 2.1 Crear y aplicar `sql/rpt/01_dim_obra.sql` (`rpt.DimObra`). Verificar 10 obras, ninguna de la empresa de pruebas, estado, delegación, tipo y jefe de obra con nombre, y fechas vacías como NULL
- [x] 2.2 Crear y aplicar `sql/rpt/02_dim_capitulo.sql` (`rpt.DimCapitulo`). Verificar 10 capítulos, sin `00` ni `99`, y solo el `10` marcado como coste indirecto
- [x] 2.3 Crear y aplicar `sql/rpt/03_fact_presupuesto.sql` (`rpt.FactPresupuesto`). Verificar que los totales por obra coinciden con el script de la tarea 1.1 y que la línea sin fecha está incluida
- [x] 2.4 Crear y aplicar `sql/rpt/04_fact_movimiento.sql` (`rpt.FactMovimiento`). Verificar que coste real y certificado por obra coinciden con el script de la tarea 1.1 y que el certificado es positivo
- [x] 2.5 Crear y aplicar `sql/rpt/05_fact_produccion.sql` (`rpt.FactProduccion`). Verificar que la suma de `ProduccionMes` y `CostePrevistoMes` por obra coincide al céntimo con el script de la tarea 1.1, que no hay filas después de la fecha de corte y que el capítulo `10` tiene coste previsto pero no producción
- [x] 2.6 Verificar que las cinco vistas se aplican desde cero en orden sin errores, y enseñar la tabla de cuadre por obra de las tareas 2.3 a 2.5

## 3. Modelo semántico

- [x] 3.1 **(Persona)** En Power BI Desktop: desactivar las tablas de fechas automáticas del fichero, quitar la tabla de prueba `CONSTRUCTORA DEMO$Job`, crear los parámetros `Servidor` y `BaseDatos`, cargar las cinco vistas `rpt` en modo importación usando esos parámetros y guardar `Obras.pbip`. Verificar que el modelo se actualiza sin errores
- [x] 3.2 Conectar el MCP de modelado a Desktop y comprobar que hay cinco tablas `rpt`, ninguna tabla `LocalDateTable` y que ninguna consulta tiene el servidor escrito. Verificar también con `git grep` que ningún fichero del proyecto tiene credenciales
- [x] 3.3 Crear con el MCP la tabla `Calendario`, marcarla como tabla de fechas y crear las relaciones de `design.md`. Verificar con una consulta DAX que el coste real por año coincide con SQL
- [x] 3.4 Crear con el MCP las medidas de `FactPresupuesto` y `FactMovimiento`, con descripción y formato español. Verificar sus valores por obra con DAX frente al script de la tarea 1.1
- [x] 3.5 Crear con el MCP las medidas de `FactProduccion`, incluidas las de color. Verificar con DAX que `OB-26003` no da error, que las medidas en porcentaje quedan en blanco cuando el denominador es 0 y que las de color devuelven rojo solo cuando corresponde

## 4. Prueba del modelo frente al ERP

- [x] 4.1 Ejecutar con el MCP una consulta DAX con todas las medidas por obra y compararla con `sql/test/control_economico.sql`. Dejar la tabla de comparación en `openspec/changes/informe-control-economico/prueba.md`. Verificar diferencia 0,00 € en todas las obras e indicadores
- [x] 4.2 Comprobar con DAX, a nivel obra × capítulo, que la suma de capítulos es igual a la obra en cada medida aditiva y que la desviación % de cada capítulo coincide con SQL. Añadirlo a `prueba.md`

## 5. Informe

- [x] 5.1 **(Persona)** _(Generada por el agente en PBIR a petición de la persona; revisada en Desktop por la persona.)_ Borrar las páginas de prueba. Crear la página **Resumen**: tarjetas de presupuesto de coste, presupuesto de venta, coste real, producción, certificado, pendiente de certificar y margen real, más una tabla por obra con esas medidas, márgenes previsto y real con sus porcentajes, y desviación de coste y su porcentaje. Formato condicional de margen real con `Color margen real`. Verificar que la tabla se ordena por pendiente de certificar
- [x] 5.2 **(Persona)** _(Generada por el agente en PBIR a petición de la persona; revisada en Desktop por la persona.)_ Crear la página **Detalle obra × capítulo**: matriz con obra y capítulo en filas y presupuesto de coste, coste real, producción, coste previsto, desviación y desviación %. Formato condicional con `Color desviación`. Verificar que un capítulo con más del 5 % sale en rojo y uno con menos no
- [x] 5.3 **(Persona)** _(Generada por el agente en PBIR a petición de la persona; revisada en Desktop por la persona.)_ Crear la página **Evolución mensual**: gráfico de columnas o líneas por `Calendario[AñoMes]` con coste real, producción y certificado. Verificar que, filtrando una obra, la suma de los meses coincide con la tabla resumen
- [x] 5.4 **(Persona)** _(Generada por el agente en PBIR a petición de la persona; revisada en Desktop por la persona.)_ En las tres páginas, añadir segmentaciones de delegación, tipo de obra, jefe de obra y estado, sincronizadas entre páginas, y una tarjeta con `Fecha de corte`. Verificar que cada filtro afecta a todos los visuales de la página
- [x] 5.5 Revisar el `git diff` de `Obras.SemanticModel/` y `Obras.Report/`: sin credenciales, sin servidor escrito fuera de los parámetros y sin tablas de fechas automáticas

## 6. Publicación

- [ ] 6.1 **(Persona)** Publicar desde Power BI Desktop en el área de trabajo de la demo. Verificar que los totales de la página Resumen en el servicio coinciden con los de Desktop
- [ ] 6.2 Recorrer los criterios de aceptación del issue `docs/issues/01-control-economico.md` sobre el informe publicado y anotar en `prueba.md` el resultado de cada uno

## Workflow follow-up

- Archivar el cambio con `/openspec-archive-change` cuando la prueba esté completa.
- Commit de `sql/`, `Obras.SemanticModel/`, `Obras.Report/` y `openspec/`.
