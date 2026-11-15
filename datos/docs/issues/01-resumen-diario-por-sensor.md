---
title: Resumen diario por sensor
labels: [feature, datos]
depends_on: []
---

## Contexto
Las lecturas de los sensores ya están cargadas, pero el equipo no dispone de una vista diaria de su comportamiento. El resumen permitirá a los analistas y responsables de sede revisar los valores y detectar días con datos incompletos.

## Historia de usuario
Como analista de datos, quiero consultar el resumen diario de cada sensor, para comparar su comportamiento a lo largo del tiempo.

Como responsable de sede, quiero limitar el resumen a mi sede, para revisar sus sensores sin resultados de otras instalaciones.

## Alcance
- Consultar desde la línea de comandos del proyecto, igual que `check`, el valor mínimo, máximo y medio, el número de lecturas y la unidad de cada sensor y día.
- Filtrar por sede, por sensor y por rango de fechas, también de forma combinada.
- Contar los días según UTC y presentar los resultados identificando el sensor y el día.

## Fuera de alcance
- Detección de huecos o valores anómalos.
- Exportación a fichero e informes semanales.
- Actualización o precálculo de resultados.

## Criterios de aceptación
1. Dadas las lecturas del curso, cuando se consulta el resumen de un sensor y un día con lecturas, entonces aparecen su mínimo, máximo, media, número de lecturas y unidad, calculados únicamente con las lecturas de ese día UTC.
2. Dadas lecturas de varias sedes y sensores, cuando se aplican los filtros de sede, sensor y fechas, entonces solo aparecen los sensores y días comprendidos en todos los filtros indicados.
3. Dado un día con lecturas ausentes, cuando se consulta su resumen, entonces el número de lecturas refleja las recibidas y la media se calcula con esas lecturas, sin rellenar las ausentes.
4. Dada una consulta sin resultados en el rango solicitado, cuando se ejecuta desde la línea de comandos, entonces se informa de ello sin mostrar resúmenes de otros días o sensores.

## Notas
- Con una lectura cada cinco minutos, cada sensor debería tener 288 lecturas al día; los días incompletos afectan a la media.
- La especificación debe fijar la presentación y la precisión de los valores y aclarar cómo representar los días sin ninguna lectura.
