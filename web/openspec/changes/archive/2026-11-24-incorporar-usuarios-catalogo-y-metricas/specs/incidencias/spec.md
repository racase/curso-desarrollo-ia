# Spec Delta

## MODIFIED Requirements

### Requirement: Alta en nombre propio
El sistema SHALL permitir a cualquier usuario autenticado crear una incidencia para sí mismo con título, descripción, prioridad y un activo existente obligatorio. SHALL fijar el solicitante desde la sesión, el estado inicial propuesta y la asignación vacía. SHALL ofrecer prioridad media por defecto y las opciones baja, media, alta y crítica.

#### Scenario: Alta válida para cada rol
- **WHEN** un solicitante, técnico o administrador registra título y descripción válidos y un activo existente sin cambiar la prioridad propuesta
- **THEN** se crea una incidencia vinculada al activo en estado propuesta, sin técnico, de prioridad media y con el usuario autenticado como solicitante

#### Scenario: Prioridad elegida y campos protegidos
- **WHEN** un usuario crea una incidencia con activo válido y prioridad crítica e intenta enviar otro solicitante, estado o técnico
- **THEN** la prioridad es crítica y el sistema conserva solicitante autenticado, estado propuesta y asignación vacía

### Requirement: Validación del alta
El sistema SHALL exigir título de 1 a 255 caracteres y descripción de 1 a 10000 caracteres, tras recortar espacios exteriores, una prioridad válida y un activo existente. SHALL rechazar contenido vacío o compuesto solo de espacios y activos vacíos o inexistentes, indicando los errores y sin crear la incidencia.

#### Scenario: Datos inválidos
- **WHEN** se envía título vacío, descripción de espacios, contenido sobre el límite o prioridad desconocida
- **THEN** se muestran errores en los campos afectados y no se crea ninguna incidencia

#### Scenario: Activo obligatorio y existente
- **WHEN** se intenta crear una incidencia sin activo, con un identificador inexistente o con un valor que no identifica un activo
- **THEN** se indica el error de activo y no se crea la incidencia aunque los demás campos sean válidos

### Requirement: Listado y ficha
El sistema SHALL mostrar en el listado título, estado, prioridad, solicitante, activo y sede, y en la ficha también la descripción completa. SHALL ofrecer filtros combinables por estado y prioridad aplicados exclusivamente a las incidencias accesibles. SHALL representar contenido de usuario como texto sin ejecutar marcado. Las incidencias anteriores sin vínculo SHALL mostrar «Sin activo» y «Sin sede» hasta su vinculación administrativa.

#### Scenario: Filtro de los cinco estados
- **WHEN** un usuario filtra por propuesta, abierta, en curso, resuelta o cerrada
- **THEN** el filtro reconoce cada estado y solo muestra incidencias accesibles con ese estado

#### Scenario: Filtros combinados
- **WHEN** un usuario selecciona estado abierta y prioridad alta
- **THEN** solo aparecen incidencias accesibles que cumplen ambos filtros y puede retirar los filtros para recuperar el listado accesible

#### Scenario: Sin resultados
- **WHEN** no existen incidencias accesibles que cumplan los filtros
- **THEN** aparece un estado vacío sin datos de otros usuarios

#### Scenario: Consulta del contenido
- **WHEN** un usuario abre una ficha accesible con marcado incluido en título o descripción
- **THEN** ve los datos de la incidencia, incluido el contexto de activo y sede, y el marcado se presenta como texto sin ejecutarse

#### Scenario: Identificación física sin cambiar permisos
- **WHEN** un solicitante consulta sus incidencias o un técnico o administrador consulta cualquier incidencia
- **THEN** el listado y la ficha muestran código y nombre del activo y su sede sin ampliar la visibilidad de incidencias ajenas para solicitantes

#### Scenario: Compatibilidad de incidencias anteriores
- **WHEN** se consulta una incidencia creada antes de incorporar el catálogo que todavía no tiene activo
- **THEN** permanece accesible según los permisos existentes y muestra «Sin activo» y «Sin sede», conservando sus datos, estado, asignación, comentarios e historial
