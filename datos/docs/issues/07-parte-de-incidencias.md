---
title: Parte de incidencias de sensores
labels: [feature, datos]
depends_on: [1, 2, 3]
---

## Contexto
Los resúmenes, huecos y valores anómalos se consultan hoy por separado. El equipo de mantenimiento necesita un parte legible y verificable para revisar lo ocurrido en una sede o un sensor durante un periodo concreto.

## Historia de usuario
Como responsable de mantenimiento, quiero generar un parte con los hallazgos y sus lecturas de referencia, para decidir qué incidencias investigar.

Como responsable de sede, quiero limitar el parte a mi instalación y a unas fechas, para revisar únicamente los sensores que me corresponden.

## Alcance
- Generar desde la línea de comandos un parte en español para una sede o un sensor y un rango de fechas UTC.
- Reunir el resumen diario, los periodos sin lecturas y los valores anómalos ya definidos en los issues 1, 2 y 3.
- Mostrar, para cada hallazgo, el sensor, el momento o intervalo, el motivo y los datos concretos que permiten comprobarlo.
- Permitir guardar el parte en un fichero Markdown para revisarlo o compartirlo.
- Indicar expresamente cuándo un filtro no produce hallazgos, sin inventar incidencias.

## Fuera de alcance
- Diagnosticar la causa física de una incidencia o recomendar reparaciones.
- Modificar lecturas, emitir alertas o enviar el parte automáticamente.
- Incorporar gráficos, una aplicación web o un modelo de IA en tiempo de ejecución.

## Criterios de aceptación
1. Dada una sede, un rango UTC y lecturas con huecos o anomalías, cuando se genera el parte, entonces aparecen únicamente los sensores de esa sede y los hallazgos del periodo, con sus momentos, motivos y datos de referencia.
2. Dado un sensor y un intervalo sin incidencias detectadas, cuando se genera el parte, entonces se indica que no hay hallazgos para esos filtros y no se atribuyen problemas a otros sensores.
3. Dado un hallazgo del parte, cuando se compara con las consultas de los issues 1, 2 y 3, entonces los valores y las fechas coinciden.
4. Dado un parte guardado en Markdown, cuando se abre en un editor de texto, entonces sus secciones, filtros aplicados y resultados se pueden leer sin ejecutar el proyecto.

## Notas
- Ejercicio principal de integración para el curso de desarrollo con IA: especificar, implementar y comprobar un resultado que combina funcionalidades existentes.
- La especificación debe fijar el formato del parte, la precisión de valores, el tratamiento de días sin lecturas y cómo se aplican los límites del rango a huecos que lo cruzan.
- La IA asiste al desarrollo; la generación del parte debe ser determinista y comprobable con tests, sin necesitar una clave de API.
