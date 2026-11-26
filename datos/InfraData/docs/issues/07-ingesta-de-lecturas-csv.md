---
title: Ingesta de lecturas CSV
labels: [feature, datos]
depends_on: []
---

## Contexto
El sistema de adquisición exporta las nuevas lecturas en ficheros CSV, con una fila por lectura y su código de sensor, fecha y hora local de Europe/Madrid y valor. El equipo de datos necesita incorporarlas a InfraData con tiempos normalizados a UTC, sin duplicados y con información sobre los rechazos.

## Historia de usuario
Como analista de datos, quiero validar, normalizar y cargar las lecturas de un CSV con un informe de rechazos, para incorporar datos fiables sin duplicar lecturas al repetir una carga.

## Alcance
- Cargar en la base de datos lecturas de un CSV exportado por el sistema de adquisición.
- Validar el código del sensor, la fecha y hora y el valor antes de cargar.
- Convertir las fechas y horas locales de Europe/Madrid a UTC.
- Rechazar sensores desconocidos, valores vacíos o no numéricos y filas duplicadas.
- Generar un informe que identifique cada fila rechazada y explique su motivo.
- Evitar duplicar lecturas al cargar de nuevo el mismo fichero.
- Funcionalidad independiente de los demás issues.

## Fuera de alcance
- Crear sensores desconocidos o corregir automáticamente valores rechazados.
- Otros formatos de entrada, recepción continua de datos o API.
- Detección de anomalías, agregaciones e informes analíticos.

## Criterios de aceptación
1. Dado el conjunto fijo del curso y un CSV válido aportado por el formador con lecturas nuevas de sensores conocidos, cuando se carga, entonces cada lectura se incorpora con el sensor y valor originales y el instante UTC equivalente a su fecha y hora en Europe/Madrid.
2. Dado un CSV de comprobación con sensores desconocidos y valores vacíos o no numéricos, cuando se valida, entonces esas filas se rechazan, no se incorporan y aparecen en el informe con su identificación y motivo.
3. Dado un CSV de comprobación con filas duplicadas, cuando se procesa, entonces se informa del rechazo de las duplicadas y ninguna lectura se incorpora más de una vez, conforme a la política de carga acordada.
4. Dado un CSV cargado correctamente sobre el conjunto del curso, cuando se vuelve a cargar el mismo fichero, entonces no se añaden lecturas duplicadas y el informe identifica los rechazos correspondientes.
5. Dado un CSV de comprobación con filas válidas e inválidas, cuando se procesa, entonces la incorporación de las filas válidas y el informe de rechazos respetan la decisión especificada de carga parcial o rechazo del fichero completo.

## Notas
- El formato del fichero lo aporta el formador; fijar su contrato en la especificación antes de implementar.
- Evitar duplicados puede implicar un cambio de esquema de la base de datos. `AGENTS.md` pide preguntar antes de realizar ese cambio.
- Decidir si un fichero con errores se carga en parte o no se carga, y cómo se refleja esa decisión en el informe.
- Definir qué identifica una lectura duplicada, incluido el caso de un mismo sensor e instante con valores distintos.
- Acordar el tratamiento de fechas inválidas y horas locales ambiguas o inexistentes por los cambios de horario de Europe/Madrid, sin asignar un instante UTC arbitrario.
- Realizar las comprobaciones de carga en un entorno de pruebas, conservando el conjunto original del curso.
