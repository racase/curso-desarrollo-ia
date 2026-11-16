# Tasks

## 1. Alta y consulta de incidencias — issue 01

- [x] 1.1 Crear migración, modelo `Ticket`, relaciones, enumeraciones y factory; verificar persistencia, valores permitidos y relaciones mediante pruebas SQLite y migración/reversión en una base desechable.
- [x] 1.2 Implementar alta y validación con solicitante de sesión, estado propuesta y prioridad media por defecto; comprobar mediante pruebas los límites de campos y la imposibilidad de suplantar solicitante, estado y técnico.
- [x] 1.3 Incorporar política y consulta restringida por rol; verificar con pruebas que el solicitante no puede listar, abrir ni modificar incidencias ajenas y que técnicos y administradores pueden consultar todas.
- [x] 1.4 Crear listado, filtros combinados, paginación, alta y ficha en `TicketResource`; comprobar con pruebas Livewire la creación, columnas, filtro por propuesta, filtros combinados, resultados vacíos y acceso anónimo.
- [x] 1.5 Implementar edición de datos básicos con bloqueo y validación del estado vigente; verificar mediante pruebas la edición del solicitante en propuesta y abierta, los permisos del equipo, la inmutabilidad de solicitante y fecha, y la denegación de cambios tras el cierre.
- [x] 1.6 Actualizar `README.md` con las pantallas y reglas del issue 01; revisar que los recorridos documentados funcionan con los tres roles y mantener las pruebas existentes de acceso al panel.

## 2. Asignación y seguimiento — issue 02

- [x] 2.1 Añadir persistencia de `TicketStatusChange` y versión de seguimiento, relaciones y factories; comprobar las claves foráneas y el orden estable mediante pruebas de persistencia.
- [x] 2.2 Implementar asignación y reasignación transaccionales con versión esperada; verificar mediante pruebas permisos, destinatarios no técnicos, incidencias cerradas, retirada inválida y conservación del estado.
- [x] 2.3 Implementar el flujo propuesta → abierta → en curso → resuelta → cerrada con autorización actualizada y guardado atómico del historial; comprobar todas las parejas de estados, apertura por administrador sin técnico, denegación al solicitante, responsable obligatorio para en curso y reversión completa ante fallo del historial.
- [x] 2.4 Añadir acciones de asignación y transición e historial en la ficha; verificar con pruebas Livewire acciones permitidas, rechazo de solicitudes manipuladas, origen del alta y autor y fecha de las transiciones.
- [x] 2.5 Incorporar y ejecutar pruebas de seguimiento concurrente con conexiones independientes sobre una base PostgreSQL exclusiva de pruebas; verificar que una transición o reasignación desactualizada no sobrescribe cambios ni duplica el historial.
- [x] 2.6 Documentar en `README.md` permisos, flujo lineal, restricción de eliminación de usuarios referenciados y ejecución aislada de las pruebas PostgreSQL; comprobar los comandos documentados sin usar datos de desarrollo.

## 3. Comentarios — issue 03

- [x] 3.1 Añadir migración, modelo `TicketComment`, relaciones y factory; comprobar con pruebas persistencia, autoría, fecha y orden estable, incluidos empates de fecha.
- [x] 3.2 Implementar publicación con autorización sobre la incidencia y bloqueo compartido con las operaciones de cierre; verificar mediante pruebas acceso ajeno, autor manipulado, límites de texto y denegación en cerradas.
- [x] 3.3 Incorporar conversación paginada y formulario en la ficha, con salida escapada y sin acciones de edición ni eliminación; comprobar con pruebas Livewire texto seguro, orden, permisos y conservación de comentarios.
- [x] 3.4 Añadir y ejecutar prueba PostgreSQL de cierre concurrente con publicación; verificar con conexiones independientes que un cierre confirmado antes de publicar impide guardar el comentario.
- [x] 3.5 Documentar en `README.md` los comentarios y sus límites; comprobar el recorrido de publicación y consulta con solicitante, técnico y administrador.

## 4. Verificación integrada

- [x] 4.1 Ejecutar `docker compose exec app vendor/bin/pint --test` y `docker compose exec app php artisan test`; verificar que pasan las pruebas nuevas y las existentes de inicio de sesión.
- [x] 4.2 Verificar las migraciones completas en PostgreSQL desechable y el recorrido alta en propuesta → asignación → abierta → en curso → comentario → resuelta → cerrada con los tres roles; confirmar que cerradas quedan en consulta y un solicitante nunca accede a incidencias ajenas.
- [x] 4.3 Revisar correspondencia entre los escenarios de las tres especificaciones y las pruebas implementadas; ejecutar `openspec validate add-ticket-management --strict --no-interactive` y registrar los resultados de integración y concurrencia.

## Workflow follow-up

- Revisar la implementación antes de archivar el cambio.
- Archivar cuando la implementación esté validada y se solicite finalizar el flujo.
