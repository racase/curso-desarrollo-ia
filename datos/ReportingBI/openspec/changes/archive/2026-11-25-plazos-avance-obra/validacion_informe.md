# Validación y cierre del informe de plazos

Fecha: 25/11/2026. El usuario confirma «ya esta validado por mi, archiva el cambio y haz commit de todo». Se acepta la revisión local del proyecto y se autoriza el archivo y commit de ReportingBI. La publicación no se ha confirmado ni se da por realizada.

## Informe entregado

El proyecto activo es `ObrasModel/Obras.pbip`, con las tres páginas económicas y dos nuevas:

- `66798edd9042be6d6aa4`, **Plazos y avance**: seis indicadores, curva S acumulada y ranking ascendente por desviación, con desempate por código.
- `cba94359ad03385470ee`, **Hitos**: seis indicadores, detalle con fechas planificada y real, estado histórico y días individuales; distingue pendientes retrasados de cumplidos tarde.

Las páginas nuevas contienen 36 visuales y seis grupos de filtros compartidos con las páginas existentes. La nota visible explica la exclusión de las líneas sin fecha del numerador y denominador del avance planificado y su conservación en el presupuesto económico.

`tools/build_plazos_report.py` genera las páginas nuevas conservando los visuales anteriores. Para permitir el corte dinámico, traslada el filtro fijo de fechas del informe a las tres páginas económicas: estas mantienen su periodo original y las nuevas pueden consultar todo el calendario. La curva se limita al corte efectivo con la medida auxiliar oculta `Mostrar mes de plazos`, creada mediante MCP. Son 18 medidas nuevas en total, incluyendo esta ayuda de presentación.

## Comprobaciones realizadas

- Validación estática de 42 JSON: metadatos de cinco páginas y 36 visuales nuevos; esquemas públicos declarados, referencias TMDL, filtros, seis grupos sincronizados, orden, dimensiones y ausencia de solapamientos. Cero errores en `evidencias/validacion_pbir.json`. Los formatos nativos existentes de Desktop no se degradaron para validarlos con esquemas antiguos.
- Apertura real del PBIP en Desktop y actualización completa del modelo mediante MCP sin errores.
- Renderizado de ambas páginas: al corte 31/10/2026, planificado 83,5 %, real 80,8 %, desviación −2,8 puntos, índice 0,97 y un hito pendiente retrasado 31 días. El ranking sitúa primero OB-25006 con −20,2 puntos.
- Filtro de delegación Norte aplicado en Plazos y avance y comprobado en Hitos: cero pendientes retrasados, cuatro cumplidos tarde, diez a tiempo y seis pendientes.
- Corte 15/10/2026 seleccionado en la interfaz: un hito retrasado con máximo pendiente de 15 días; la curva incluye el mes parcial y muestra 79,6 % planificado y 76,4 % real. Las capturas de este caso conservan abierto el calendario de selección.
- Los casos de obra futura, índice vacío con planificado cero y estados históricos están contrastados SQL/DAX en la validación del modelo. La prueba adicional de seleccionar la obra futura en la interfaz fue interrumpida; no se presenta una captura inexistente como evidencia. La revisión local restante queda aceptada por la validación expresa del usuario.

Las capturas `plazos_desktop02.png`, `hitos_desktop02.png`, `norte_hitos02.png`, `corte_parcial_hitos02.png` y `corte_parcial_plazos02.png` pertenecen a ventanas reales de Power BI. Se utilizó captura exclusiva de la ventana cuando el foco no estaba disponible; no se capturaron otras aplicaciones como evidencia del informe.

## Preservación y cierre

La generación mantuvo idénticos los 45 visuales económicos respecto a su estado local anterior; las huellas y los metadatos adaptados están en `preservacion_visuales01.json`. El commit incorpora también los cambios locales de guardado de Desktop ya presentes antes del desarrollo de estas páginas, autorizados con «commit de todo». Las vistas SQL 01–05 no se modificaron. Se conservan las evidencias de conciliación NAV, SQL/DAX y regresión económica.

Se archiva por instrucción expresa del usuario con **17/18 tareas completas**. La tarea 6.1 de publicación queda abierta, como excepción de cierre registrada: no consta destino ni validación en el servicio y no se atribuye una publicación a la persona. No se publica desde el agente.

La especificación nueva se sincroniza a `openspec/specs/plazos-avance-obra/spec.md`. El archivo incluye todas las evidencias disponibles y la aprobación local del usuario; el commit abarca el proyecto ReportingBI, excluyendo cachés, diagnósticos del shell y archivos del proyecto InfraData.
