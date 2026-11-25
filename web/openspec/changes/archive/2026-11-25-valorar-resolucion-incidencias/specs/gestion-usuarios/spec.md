## MODIFIED Requirements

### Requirement: Eliminación con integridad
El sistema SHALL solicitar confirmación para eliminar una cuenta. SHALL impedir eliminar la propia cuenta o cualquier usuario referenciado como solicitante, técnico asignado, técnico del cierre, autor de comentario o actor del historial. SHALL explicar el bloqueo sin borrar ni modificar información relacionada, incluso si la cuenta referenciada como técnico del cierre ya no tiene rol técnico ni asignaciones actuales.

#### Scenario: Cuenta sin referencias
- **WHEN** un administrador confirma eliminar otra cuenta sin referencias y la operación conserva un administrador
- **THEN** la cuenta desaparece del listado y ya no permite iniciar sesión

#### Scenario: Eliminación propia
- **WHEN** un administrador intenta eliminar su cuenta
- **THEN** se rechaza la operación incluso mediante petición directa

#### Scenario: Referencias protegidas
- **WHEN** se intenta eliminar una cuenta vinculada a una incidencia, asignación, comentario o transición
- **THEN** se conserva la cuenta y toda la información asociada y se indica el motivo

#### Scenario: Técnico del cierre protegido
- **WHEN** se intenta eliminar una cuenta conservada como técnico del cierre, incluso sin asignaciones actuales o tras cambiar de rol
- **THEN** se rechaza la eliminación con una explicación y se conserva la atribución y cualquier valoración recibida
