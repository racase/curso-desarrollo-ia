# ticket-comments Specification

## Purpose

Conservar las aclaraciones de los usuarios en la ficha de cada incidencia, con autoría identificable y las mismas restricciones de acceso que sus datos.

## Requirements

### Requirement: Publicación autorizada
El sistema SHALL permitir publicar comentarios a los usuarios con acceso a una incidencia no cerrada, sin exigir asignación. El autor será el usuario conectado y la fecha se establecerá automáticamente; no podrán sustituirse mediante datos enviados por el cliente.

#### Scenario: Aclaración del solicitante
- **WHEN** un solicitante añade un comentario válido a su incidencia abierta
- **THEN** se publica con su identidad y la fecha de publicación

#### Scenario: Aportación del equipo
- **WHEN** un técnico no asignado o un administrador comenta una incidencia resuelta
- **THEN** se publica el comentario con la identidad del usuario conectado

#### Scenario: Publicación ajena
- **WHEN** un solicitante intenta comentar una incidencia de otra persona mediante su identificador
- **THEN** se deniega la operación sin crear comentarios

#### Scenario: Autoría manipulada
- **WHEN** un usuario autorizado envía un comentario indicando otro autor o fecha
- **THEN** el comentario conserva el usuario conectado como autor y la fecha automática

### Requirement: Contenido válido y seguro
El sistema SHALL admitir comentarios de texto plano de 1 a 5.000 caracteres después de retirar espacios exteriores y rechazar contenido vacío. El contenido se mostrará como texto sin ejecutar HTML ni scripts.

#### Scenario: Comentario vacío o excesivo
- **WHEN** se envía solo espacio en blanco o más de 5.000 caracteres
- **THEN** se muestra un error de validación y no se publica el comentario

#### Scenario: Texto con etiquetas
- **WHEN** se consulta un comentario que contiene etiquetas HTML o código de script
- **THEN** se muestra ese contenido como texto sin ejecutarlo

### Requirement: Consulta cronológica
El sistema SHALL mostrar los comentarios en la ficha, paginados y del más antiguo al más reciente, con autor y fecha visibles. Los empates de fecha conservarán un orden estable. Su consulta tendrá las mismas restricciones de acceso que la incidencia.

#### Scenario: Lectura de la conversación
- **WHEN** un usuario autorizado consulta una incidencia con varios comentarios
- **THEN** puede recorrerlos cronológicamente e identificar autor y fecha de cada aportación

#### Scenario: Consulta ajena
- **WHEN** un solicitante intenta consultar comentarios de una incidencia ajena
- **THEN** se deniega el acceso sin revelar la conversación

### Requirement: Conservación y cierre
El sistema SHALL impedir editar o eliminar comentarios publicados. Una incidencia cerrada conservará sus comentarios en consulta y rechazará nuevas publicaciones, incluso si el cierre ocurre mientras se prepara un comentario.

#### Scenario: Corrección posterior
- **WHEN** el autor o un administrador intenta editar o eliminar un comentario publicado
- **THEN** se rechaza la operación y se conserva el comentario original

#### Scenario: Incidencia cerrada
- **WHEN** un usuario intenta publicar un comentario después del cierre
- **THEN** no se publica y los comentarios previos siguen siendo consultables por los usuarios autorizados

#### Scenario: Cierre concurrente
- **WHEN** se cierra la incidencia antes de confirmar la publicación de un comentario preparado previamente
- **THEN** la publicación se rechaza sin reabrir la incidencia ni alterar los comentarios existentes
