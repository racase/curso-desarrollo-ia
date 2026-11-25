## Context

`TransitionIncident` bloquea la incidencia y confirma estado e historial en una transacción. El ciclo termina en Cerrada, sin reapertura. `AssignIncident` permite reasignar en cualquier estado, por lo que `technician_id` no identifica de forma estable al técnico del cierre. `IncidentPolicy` distingue permisos por rol, mientras que cualquier rol puede crear incidencias: ser el creador no basta para conceder la nueva acción, reservada al rol solicitante.

La ficha utiliza acciones Filament en `ViewIncident`; el dashboard descubre widgets automáticamente. `IncidentStatusOverview` ofrece un patrón de autorización y actualización cada 30 segundos. La eliminación de usuarios protege referencias existentes, pero todavía no conoce la nueva atribución histórica. Las pruebas funcionales usan SQLite; las garantías reales de bloqueo se verifican en PostgreSQL con procesos independientes, como en `tests/Support/`.

## Goals / Non-Goals

**Goals:**

- Valorar voluntariamente una única vez, con aislamiento entre solicitantes y validación en servidor.
- Atribuir cada puntuación a un técnico estable y mostrar medias interpretables exclusivamente al administrador.
- Incorporar incidencias antiguas sin borrar información ni alterar asignaciones o estados.

**Non-Goals:**

- Editar, borrar o comentar valoraciones; enviar avisos; introducir nuevos estados o restricciones de reasignación; añadir integraciones.

## Decisions

### 1. Persistencia en la incidencia

Añadir `closing_technician_id` nullable con clave foránea a usuarios y eliminación restringida, `resolution_rating` nullable y `rated_at` nullable. La puntuación es un entero entre 1 y 5; null significa sin valoración. Añadir restricciones compatibles con PostgreSQL y SQLite que impidan valores fuera de rango y una puntuación sin fecha, sin estado cerrado o sin técnico del cierre. Indexar la referencia histórica para consultar agregados y referencias de cuentas.

Una sola puntuación por fila evita duplicados estructuralmente. La acción debe impedir sobrescrituras: la estructura sola no garantiza inmutabilidad. Se descarta una tabla de valoraciones porque no hay múltiples aportaciones ni ciclo propio de edición. No incluir estos atributos en los datos aceptados por las acciones de alta o reasignación.

### 2. Atribución al cerrar e inicialización histórica

Dentro de la transacción de `TransitionIncident`, al pasar a Cerrada copiar el técnico asignado a `closing_technician_id`. El bloqueo existente serializa cierre y reasignación; el fallo del historial también revierte esta captura. Una reasignación posterior conserva la captura, incluso antes de recibir puntuación. No se cambia el mapa de estados ni se exige una valoración al cerrar.

Para incidencias ya cerradas, inicializar una sola vez la referencia con el técnico asignado al ejecutar la migración. El sistema no conserva un historial de asignaciones que permita reconstruir al técnico del cierre original. Si no hay técnico, conservar null y no habilitar valoración, incluso si se asigna después. No intentar capturar la referencia al primer envío: trasladaría la atención a quien estuviera asignado en ese momento.

### 3. Acción autorizada y envío único

Incorporar una acción `RateIncident` y una capacidad `rate` en `IncidentPolicy`. Exigir sesión, rol solicitante, propiedad de la incidencia, estado Cerrada, técnico del cierre y ausencia de valoración. Bloquear y releer la incidencia dentro de la transacción, volver a autorizar y validar estrictamente un entero entre 1 y 5. Guardar puntuación y fecha conjuntamente; tomar usuario y técnico del servidor, sin aceptar atribuciones del formulario.

Un segundo envío, incluida una carrera entre pestañas, debe devolver un conflicto comprensible y conservar la primera puntuación. El bloqueo de fila evita dos envíos aceptados. Administradores y técnicos no valoran aunque hayan creado la incidencia. El acceso directo a la acción debe aplicar las mismas reglas que la ficha.

### 4. Presentación de la valoración

En `ViewIncident`, ofrecer «Valorar atención» solo al solicitante elegible. Usar un grupo de cinco opciones con estrellas, etiquetas «1 estrella» a «5 estrellas», navegación por teclado, foco visible y confirmación de envío definitivo. No preseleccionar puntuación. Tras guardar, mostrar al propietario con rol solicitante la puntuación y la fecha en lectura, también al recargar; ocultar la acción. No añadir consulta individual de puntuaciones para otros roles.

Usar componentes Filament y, si hace falta, una vista Blade pequeña sin nuevas dependencias. Seguir `DESIGN.md`: Rubik, superficies claras, texto violeta oscuro, botón primario `#150f23`, controles táctiles de al menos 44px. Las estrellas seleccionadas se distinguen por forma y texto, sin introducir amarillo ni depender solo del color. Validar 320px, ampliación del 200%, teclado y lector de pantalla.

### 5. Resumen administrativo

Crear un widget de tabla «Valoraciones por técnico», descubierto por el panel, con nombre, media sobre 5 y recuento. Incluir usuarios con rol técnico actual o referenciados como técnico del cierre; marcar los segundos como «Histórico» cuando ya no tengan rol técnico. Ordenar por nombre e identificador, sin ranking; paginar para no renderizar todas las filas de una vez. Si no hay filas, indicar «No hay técnicos».

Calcular AVG y COUNT en base de datos agrupando por `closing_technician_id`, mediante una subconsulta agregada y unión a usuarios para evitar consultas por fila. Contar únicamente puntuaciones no nulas; mostrar una cifra decimal con formato español (por ejemplo, «4,5 / 5») o «Sin valoraciones» y 0. Las incidencias sin valorar no reducen la media. Los filtros del listado de incidencias no intervienen.

Exigir rol administrador tanto en `canView()` como al consultar los datos y ejecutar peticiones Livewire. Calcular al cargar/recargar y cada `30s.visible`, sin caché compartida entre sesiones. Revalidar el rol en cada petición para impedir que una sesión degradada siga obteniendo agregados. La media no se escribe ni se mantiene mediante contadores.

### 6. Integridad de usuarios

Extender `DeleteUser` con la relación de incidencias cuyo técnico del cierre es la cuenta y un mensaje explícito, además de la restricción foránea para carreras. Conservar los bloqueos actuales de eliminación. El cambio de rol sigue permitido tras reasignar todas las incidencias actuales; la referencia histórica no lo bloquea ni modifica la atribución. Mantener en el widget a la cuenta histórica evita perder puntuaciones al cambiar de rol.

## Risks / Trade-offs

- Atribución de incidencias antiguas aproximada → documentar que se utiliza el técnico existente durante la migración y verificar el resultado con datos de prueba.
- SQLite no demuestra bloqueo de filas → verificar envío simultáneo y cierre frente a reasignación con PostgreSQL y procesos independientes.
- El técnico histórico mantiene una referencia que impide eliminar su cuenta → explicar el motivo en la gestión de usuarios y permitir cambios de rol conforme a las reglas existentes.
- Media basada en pocas muestras → acompañarla siempre del recuento y no presentarla como un ranking.
- Tabla de widgets o controles personalizados pueden perder accesibilidad → comprobar semántica, teclado, foco y tamaños con la interfaz real.

## Migration Plan

1. Ejecutar una migración aditiva que añada los campos y restricciones e inicialice exclusivamente las incidencias ya cerradas. No inventar puntuaciones ni modificar fechas de atención.
2. Coordinar la migración y activación del código sin escrituras concurrentes durante la inicialización histórica, usando una breve ventana de mantenimiento en este entorno de demostración. El código que captura el cierre debe estar activo antes de reabrir el acceso.
3. Verificar en PostgreSQL y SQLite las incidencias cerradas con y sin técnico y las no cerradas. Ejecutar pruebas funcionales y de concurrencia, Pint y comprobación visual. Construir assets si cambian vistas o estilos que lo requieran.
4. En una reversión de código, conservar las columnas y los datos. El `down` elimina primero las restricciones y después los campos; ejecutarlo solo si se acepta perder las valoraciones y su atribución, conservando una copia antes.

## Open Questions

No quedan decisiones bloqueantes. Se adoptan las reglas propuestas al continuar este flujo: envío definitivo, atribución al cierre e inclusión de incidencias antiguas con su asignación en la migración. El decimal único, la actualización cada 30 segundos y la presentación de técnicos históricos son decisiones de esta propuesta revisables antes de implementar.
