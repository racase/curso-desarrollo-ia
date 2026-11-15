# Design

## Context

Las lecturas están en una hypertable de TimescaleDB. La consulta actual usa `time_bucket` y agrupa por sensor y día UTC. No se deben modificar `db/init/*.sql` ni recrear el volumen.

## Goals / Non-Goals

**Goals:** precalcular días cerrados, mantener equivalencia exacta y dar una ruta de migración repetible.

**Non-Goals:** cambiar las métricas, el formato de CLI o el conjunto de datos del curso.

## Decisions

- Crear un agregado continuo diario por identificador de sensor con mínimo, máximo, media y recuento. La consulta une después los metadatos del sensor, por lo que conserva filtros y unidad sin duplicarlos en el agregado.
- Calcular la media a partir de valores decimales en ambas rutas. La agregación distribuida de valores de coma flotante puede variar unas unidades en el último bit; la representación decimal fija una referencia reproducible sin cambiar la salida a dos decimales.
- Mantener la parte materializada hasta el inicio del día UTC actual y habilitar agregación en tiempo real para el día abierto. Así las consultas históricas no recorren lecturas y el día actual refleja inserciones inmediatamente.
- Usar una política con inicio abierto para incluir inserciones históricas, fin en el momento presente y ejecución cada minuto. TimescaleDB solo refresca días completos; el día abierto queda en la parte de tiempo real. Se admite hasta dos minutos de retraso para días cerrados en condiciones normales del planificador.
- Proveer una migración Python explícita e idempotente en el paquete; no tocar el SQL de inicialización. El comando crea el objeto, la política y llena los días cerrados. Una segunda ejecución comprueba el objeto y refresca sin duplicarlo.
- Conservar la agregación directa como función de referencia para pruebas y comparación; el comando de resumen usa el agregado precalculado tras migrar.

## Risks / Trade-offs

- El planificador puede retrasarse bajo carga → comprobar su estado y permitir ejecutar la migración para refrescar manualmente.
- El día actual todavía lee las lecturas recientes → el volumen de ese tramo se limita a un día, no a todo el historial.

## Migration Plan

1. Ejecutar `uv run python -m infradata migrate` con el contenedor existente.
2. Comprobar equivalencia completa y que el comando de resumen funciona.
3. Para revertir, volver a la consulta directa y retirar el agregado continuo y su política en una migración posterior; las lecturas originales permanecen intactas.
