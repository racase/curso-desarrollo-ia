# Spec Delta

## Purpose

Reunir las aclaraciones y respuestas en la ficha de cada incidencia, identificando autor y fecha y respetando el acceso a la incidencia.

## ADDED Requirements

### Requirement: Publicación autorizada
El sistema SHALL permitir comentar en cualquier estado a todo usuario autenticado con acceso a la incidencia. SHALL obtener autor y fecha del servidor y vincular el comentario a la incidencia autorizada. Todos los comentarios SHALL ser visibles para quienes puedan consultar esa incidencia.

#### Scenario: Participación por rol y estado
- **WHEN** el solicitante propietario, un técnico o un administrador comenta una incidencia accesible en estado propuesta, abierta, en curso, resuelta o cerrada
- **THEN** se guarda el comentario con la incidencia correcta, autor autenticado y fecha del servidor

#### Scenario: Petición manipulada
- **WHEN** un solicitante intenta comentar una incidencia ajena o alterar autor, fecha o incidencia destino
- **THEN** no puede publicar sobre una incidencia inaccesible ni suplantar al autor o la fecha

### Requirement: Contenido válido y seguro
El sistema SHALL aceptar texto plano de 1 a 5000 caracteres tras recortar espacios exteriores. SHALL rechazar texto vacío, de solo espacios o superior al límite sin guardar el comentario. SHALL mostrar contenido como texto sin ejecutar marcado.

#### Scenario: Comentario inválido
- **WHEN** se envía contenido vacío, solo espacios o superior a 5000 caracteres
- **THEN** se muestra un error y no se añade ningún comentario

#### Scenario: Marcado en el comentario
- **WHEN** un comentario contiene etiquetas o código de navegador
- **THEN** se muestra como texto sin ejecutarse

### Requirement: Conversación ordenada e inmutable
El sistema SHALL mostrar comentarios del más antiguo al más reciente, con autor y fecha y orden estable si coinciden las fechas. No SHALL ofrecer edición ni eliminación a ningún rol. La consulta SHALL respetar el mismo acceso que la ficha.

#### Scenario: Consulta de conversación
- **WHEN** un usuario con acceso abre una ficha con comentarios de varios autores
- **THEN** ve textos, autores y fechas en orden cronológico estable

#### Scenario: Sin comentarios
- **WHEN** un usuario consulta una incidencia accesible sin comentarios
- **THEN** ve un estado vacío y puede publicar la primera aportación

#### Scenario: Modificación o acceso ajeno
- **WHEN** cualquier rol intenta editar o eliminar un comentario, o un solicitante consulta comentarios de una incidencia ajena
- **THEN** se deniega la operación sin modificar ni exponer información no autorizada
