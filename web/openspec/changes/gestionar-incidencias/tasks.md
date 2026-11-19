# Tasks

## 1. Alta y consulta de incidencias — issue 01

- [x] 1.1 Crear enums de estado y prioridad, modelo Incident, migración aditiva, relaciones de User y factory; verificar persistencia de los cinco estados y las cuatro prioridades y claves foráneas en tests con RefreshDatabase.
- [x] 1.2 Implementar consulta visible por usuario, IncidentPolicy y acción de alta con solicitante autenticado, estado propuesta y asignación vacía; verificar en Feature tests los tres roles, aislamiento entre solicitantes y payloads que intenten alterar campos protegidos.
- [x] 1.3 Validar título 1–255, descripción 1–10000 y prioridad válida tras recortar espacios; verificar límites aceptados, espacios y excesos rechazados y ausencia de registros ante errores.
- [x] 1.4 Crear IncidentResource con listado, alta y ficha, etiquetas españolas y filtros combinables por estado y prioridad; verificar con Livewire columnas, descripción en ficha, estado vacío, valores por defecto y filtros sin fugas de datos.
- [x] 1.5 Denegar edición/borrado de incidencias y accesos directos a fichas ajenas, mantener autenticación y escapar texto; verificar peticiones no autorizadas, redirección de invitados y contenido con marcado inerte en Feature tests.
- [x] 1.6 Actualizar README con alta, consulta y visibilidad por rol, sustituyendo las afirmaciones de que no existen incidencias ni permisos; verificar que las rutas y el comportamiento documentados coinciden con el recurso.

## 2. Asignación y seguimiento — issue 02

- [x] 2.1 Crear modelo, migración y factory de IncidentStatusHistory con relaciones, estados, actor, fecha e índices; verificar orden de creación de tablas, relaciones y ausencia de historial al dar de alta una incidencia.
- [x] 2.2 Implementar asignación/reasignación administrativa y autoasignación transaccional de incidencias libres por técnicos, validando rol del destino; verificar permisos por rol, rechazo de usuarios inválidos, ausencia de desasignación y conservación del estado con tests de dominio/Feature.
- [x] 2.3 Implementar transición con estado de origen esperado, bloqueo y mapa propuesta → abierta → en curso → resuelta → cerrada; verificar todas las parejas origen/destino y permisos de administrador, técnico asignado, otro técnico y solicitante, incluida apertura desde propuesta por administrador sin asignación, rechazo de saltos desde propuesta y rechazo del paso a en curso sin técnico.
- [x] 2.4 Guardar estado e historial en una transacción; verificar exactamente una entrada por avance, actor y fecha correctos, rechazo de peticiones obsoletas y rollback de ambos cambios ante fallo de historial.
- [x] 2.5 Añadir responsable, acciones de asignación y siguiente estado e historial de solo lectura a la ficha; verificar con Livewire visibilidad, ejecución autorizada, denegación de llamadas manipuladas, orden del historial y avisos de conflicto.
- [x] 2.6 Añadir y ejecutar comprobación reproducible con dos conexiones en PostgreSQL exclusivo de pruebas para autoasignación simultánea y transición duplicada; verificar un solo ganador y una sola entrada, documentando comando y configuración aislada sin secretos ni uso de la base de demo.
- [x] 2.7 Documentar en README roles autorizados, transiciones, ausencia de reapertura y restricción de borrado de usuarios referenciados para el futuro issue 04; verificar coherencia con las specs y las pruebas de seguimiento.

## 3. Comentarios — issue 03

- [x] 3.1 Crear IncidentComment, migración, relaciones y factory con incidencia, autor y fecha; verificar persistencia y orden estable por fecha e id con pruebas de relaciones y consulta.
- [x] 3.2 Implementar política y acción de publicación con autor/fecha de servidor, destino autorizado y texto de 1–5000 caracteres; verificar publicación por cada rol en los cinco estados, límites, espacios, destino ajeno y manipulación de autor o fecha.
- [x] 3.3 Integrar formulario y conversación paginada en la ficha mostrando autor y fecha, con texto escapado y sin edición/borrado; verificar con Livewire orden, estado vacío, marcado inerte, continuidad entre páginas y denegación de cambios o consultas no autorizados.
- [x] 3.4 Documentar publicación, visibilidad compartida e inmutabilidad de comentarios en README; verificar que no se prometen adjuntos, notificaciones ni comentarios privados.

## 4. Integración y revisión

- [x] 4.1 Ejecutar docker compose exec app vendor/bin/pint y docker compose exec app php artisan test; verificar suite completa correcta, incluido AdminLoginTest, y revisar el diff final sin cambios ajenos al alcance.
- [x] 4.2 Validar migraciones aditivas en PostgreSQL aislado y revisar el flujo completo con administrador, técnico y dos solicitantes en el panel; verificar separación de acceso, asignación, cierre e intercambio de comentarios y conservar capturas para la futura PR.
- [x] 4.3 Contrastar implementación con las tres specs, registrar resultados y ejecutar openspec validate gestionar-incidencias --strict; verificar que cada escenario tiene evidencia y que la validación termina sin errores.
