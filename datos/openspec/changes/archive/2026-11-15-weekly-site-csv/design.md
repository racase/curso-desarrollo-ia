# Design

## Context

El resumen diario devuelve filas agregadas con sede, sensor, unidad y métricas. La exportación solo necesita formatearlas.

## Goals / Non-Goals

**Goals:** generar un fichero estable y legible en Excel en español.

**Non-Goals:** producir hojas de cálculo o añadir métricas semanales nuevas.

## Decisions

- La fecha de semana debe ser lunes; los siete días se cuentan en UTC. Se evita la ambigüedad de indicar un día arbitrario o un número de semana.
- Usar el módulo `csv` de la biblioteca estándar con `;`, comillas cuando hagan falta y saltos CRLF. No se añade dependencia.
- Escribir con `utf-8-sig` para incluir BOM y facilitar la detección de codificación de Excel. Los decimales se formatean con coma y dos cifras.
- Crear el destino en modo exclusivo para evitar sobrescrituras accidentales.

## Risks / Trade-offs

- El BOM puede no ser deseado por algunos importadores → documentar el formato para consumidores automáticos.
