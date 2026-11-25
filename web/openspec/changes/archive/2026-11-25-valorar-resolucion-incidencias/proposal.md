## Why

El cierre de una incidencia no recoge la satisfacción del solicitante con la atención recibida. El issue [07](../../../../docs/issues/07-valoracion-de-la-resolucion.md) propone recoger esa valoración y ofrecer al administrador una media por técnico para interpretar la calidad de la atención.

## What Changes

- Valoración voluntaria de 1 a 5 estrellas en la ficha de una incidencia cerrada, exclusiva de su solicitante, con un único envío sin edición posterior.
- Conservación del técnico asignado al cerrar para atribuir la atención aunque se reasigne posteriormente. Para incidencias ya cerradas se toma la asignación existente al incorporar la funcionalidad; las que carezcan de técnico quedan sin posibilidad de valorar.
- Widget exclusivo de administradores con media sobre 5 a un decimal y número de valoraciones por técnico, incluyendo «Sin valoraciones», actualizado al cargar y cada 30 segundos mientras sea visible.
- Conservación de la atribución histórica ante cambios de rol y protección de las cuentas referenciadas frente a su eliminación.
- Presentación accesible mediante teclado y lector de pantalla, coherente con `DESIGN.md`.
- Fuera de alcance: edición o eliminación de valoraciones, reseñas, notificaciones, rankings, exportaciones y cambios en el ciclo de estados o las reglas de reasignación.

## Capabilities

### New Capabilities

- `valoracion-resolucion`: elegibilidad, envío único, atribución histórica, consulta de la puntuación propia y resumen administrativo por técnico.

### Modified Capabilities

- `gestion-usuarios`: extender la protección de eliminación a las cuentas conservadas como técnico del cierre.

## Impact

- Persistencia de incidencias: nueva referencia histórica, puntuación y fecha; migración aditiva con inicialización de incidencias cerradas existentes.
- Cierre transaccional, autorización y acción de valoración; ficha Filament y nuevo widget del dashboard.
- Gestión de usuarios: bloqueo explícito de eliminación de técnicos históricos, sin añadir restricciones al cambio de rol.
- Pruebas de persistencia, permisos, concurrencia, medias, Livewire y regresión; verificación visual en móvil y capturas.
- Sin nuevas integraciones ni dependencias externas previstas.
