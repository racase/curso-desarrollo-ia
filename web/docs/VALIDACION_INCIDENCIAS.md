# Validación de incidencias

Cambio: [gestionar-incidencias](../openspec/changes/gestionar-incidencias/proposal.md).

## Comprobaciones realizadas

- `docker exec infraticket-app php artisan test --compact`: 39 pruebas y 532 aserciones correctas, incluidos los tests de autenticación existentes.
- `docker exec infraticket-app vendor/bin/pint --test`: formato correcto.
- `docker exec infraticket-app php tests/Support/check-postgres-concurrency.php`: migraciones verificadas en una base PostgreSQL temporal, dos conexiones realmente compitiendo por la misma fila, un ganador y un conflicto tanto en asignación como en transición, sin historial duplicado.
- `openspec validate gestionar-incidencias --strict`: cambio válido.
- Migraciones aditivas aplicadas en la demo sobre Podman; `/admin/login` responde HTTP 200.
- Validación funcional manual confirmada por el usuario antes de solicitar commit, push y PR.

## Cobertura de las especificaciones

| Capacidad | Evidencia automatizada |
|---|---|
| Alta y persistencia de los cinco estados y cuatro prioridades | `IncidentPersistenceTest`, `IncidentAccessTest` |
| Campos protegidos, límites y valores por defecto | `IncidentAccessTest`, `IncidentPanelTest` |
| Visibilidad por rol, URL directa, filtros combinados y estados vacíos | `IncidentAccessTest`, `IncidentPanelTest` |
| Asignación, reasignación, autoasignación y rechazo de destinos inválidos | `IncidentTrackingTest`, `IncidentTrackingPanelTest`, comprobación PostgreSQL |
| Matriz de transiciones, permisos y técnico obligatorio para iniciar | `IncidentTrackingTest` |
| Historial atómico, actor y fecha, rollback y confirmaciones obsoletas | `IncidentTrackingTest`, `IncidentTrackingPanelTest`, comprobación PostgreSQL |
| Comentarios por cada rol autorizado en los cinco estados, autoría y límites | `IncidentCommentsTest` |
| Orden, paginación, texto escapado, inmutabilidad y aislamiento de comentarios | `IncidentCommentsTest`, `IncidentCommentsPanelTest` |

## Capturas

- [Ficha del solicitante y comentarios](screenshots/incidencia-solicitante.png).
- [Ficha del técnico](screenshots/incidencia-tecnico.png).

## Despliegue

Se añaden tres tablas: `incidents`, `incident_status_histories` e `incident_comments`.
Ejecutar `docker compose exec app php artisan migrate` en instalaciones existentes.
No se necesitan paquetes ni variables de entorno nuevos. Las referencias a usuarios
restringen su eliminación para conservar solicitante, responsable y autoría.
