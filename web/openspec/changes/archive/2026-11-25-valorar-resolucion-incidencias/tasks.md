## 1. Persistencia y atribución al cierre

- [x] 1.1 Crear la migración aditiva de `closing_technician_id`, `resolution_rating` y `rated_at`, con referencia protegida, índice y restricciones de integridad compatibles con PostgreSQL y SQLite según el diseño.
- [x] 1.2 Inicializar en la migración únicamente el técnico del cierre de incidencias ya cerradas con asignación, sin alterar estados, fechas existentes ni generar puntuaciones; comprobar datos anteriores y reversión en una base de prueba.
- [x] 1.3 Incorporar relaciones y casts en `Incident` y `User`, y ajustar las factories para preparar incidencias cerradas elegibles, históricas sin atribución y valoradas.
- [x] 1.4 Capturar el técnico del cierre en la transacción de `TransitionIncident`, conservándolo ante reasignaciones posteriores; extender las pruebas del cierre, incluido el rollback si falla el historial.

## 2. Autorización y valoración definitiva

- [x] 2.1 Añadir el permiso `rate` y la acción `RateIncident` con relectura bloqueada, rol y propiedad, estado cerrado, atribución existente y validación de entero de 1 a 5; obtener identidad y técnico exclusivamente del servidor.
- [x] 2.2 Implementar el guardado conjunto de puntuación y fecha y el rechazo comprensible de segundos envíos, sin sobrescritura ni cambios de estado, asignación o historial.
- [x] 2.3 Añadir pruebas funcionales de los cinco valores válidos, entradas inválidas, todos los estados previos, actores no autorizados, incidencias ajenas, atribución ausente y envío repetido.
- [x] 2.4 Extender la comprobación con PostgreSQL y procesos independientes de `tests/Support/` para demostrar un único envío aceptado en una carrera y la captura coherente del técnico ante cierre y reasignación concurrentes.

## 3. Integridad de cuentas y atribución histórica

- [x] 3.1 Extender `DeleteUser` para bloquear con una explicación las cuentas referenciadas como técnico del cierre, manteniendo los demás bloqueos y la protección por clave foránea.
- [x] 3.2 Añadir pruebas de reasignación antes y después de valorar, cambio de rol tras reasignar todas las incidencias y eliminación bloqueada por la referencia histórica, incluso sin valoración.
- [x] 3.3 Verificar que una incidencia histórica cerrada sin técnico sigue sin poder valorarse después de asignarla, y que las no cerradas en la migración capturan al técnico en su cierre posterior.

## 4. Ficha del solicitante

- [x] 4.1 Incorporar «Valorar atención» a `ViewIncident` con cinco estrellas accesibles, sin selección inicial, etiquetas textuales y aviso de envío definitivo, siguiendo `DESIGN.md` sin nuevas dependencias.
- [x] 4.2 Mostrar puntuación y fecha únicamente al propietario con rol solicitante tras el envío y al recargar; ocultar el control y resolver formularios obsoletos con mensajes comprensibles.
- [x] 4.3 Añadir pruebas Livewire de visibilidad y envío directo para cada rol, validación, confirmación, consulta propia y ausencia de puntuación individual para el equipo.

## 5. Resumen del administrador

- [x] 5.1 Crear y registrar por descubrimiento un widget de tabla «Valoraciones por técnico», con autorización al mostrar y consultar, agregación por técnico del cierre sin consultas por fila, inclusión de técnicos actuales e históricos y paginación ordenada por nombre e identificador.
- [x] 5.2 Mostrar media a un decimal sobre 5, recuento, «Sin valoraciones» y estado vacío; incorporar actualización al cargar y cada `30s.visible`, independiente de los filtros de incidencias y sin caché entre sesiones.
- [x] 5.3 Añadir pruebas del cálculo con valores distintos y puntuaciones nulas, separación entre técnicos, reasignaciones, técnicos históricos, técnicos sin valoraciones, estado vacío, orden y paginación.
- [x] 5.4 Añadir pruebas Livewire de acceso directo denegado a otros roles y anónimos, pérdida del rol administrador con el widget abierto, registro único del widget y actualización tras una valoración.

## 6. Validación y documentación

- [x] 6.1 Verificar ficha y widget en navegador a 320px y con ampliación del 200%, teclado, foco visible, etiquetas para lector de pantalla y controles táctiles de 44px; guardar capturas de las dos vistas para revisión.
- [x] 6.2 Ejecutar `docker compose exec app vendor/bin/pint --dirty` y `docker compose exec app php artisan test`, junto a las comprobaciones de concurrencia PostgreSQL; ejecutar `npm run build` si hay cambios de assets que lo requieran y conservar la salida completa de cualquier fallo.
- [x] 6.3 Documentar las reglas definitivas, la aproximación de atribución histórica, la ventana de mantenimiento para migrar y la reversión preservando los datos; registrar los comandos y resultados de validación.
