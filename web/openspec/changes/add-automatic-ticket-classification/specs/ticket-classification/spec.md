# Spec Delta

## Purpose

Proponer una clasificación de incidencias a partir de su descripción mediante un proveedor externo, tolerar sus fallos y permitir una revisión humana autorizada.

## ADDED Requirements

### Requirement: Clasificación automática al crear
El backend SHALL solicitar a OpenRouter una propuesta de categoría y urgencia basada únicamente en la descripción validada de cada nueva incidencia autorizada, usando el modelo configurado como `gpt-oss-120b`. La urgencia SHALL corresponder a la prioridad existente. Ninguna edición posterior SHALL provocar otra clasificación automática.

#### Scenario: Propuesta válida
- **WHEN** se crea una incidencia válida y OpenRouter devuelve hardware y alta conforme al esquema
- **THEN** se guarda una única incidencia con categoría hardware y prioridad alta, manteniendo solicitante, estado propuesta y ausencia de técnico inicial

#### Scenario: Entrada no autorizada o inválida
- **WHEN** el usuario no está autorizado o los datos del alta son inválidos
- **THEN** no se realiza ninguna llamada al proveedor ni se crea una incidencia

#### Scenario: Clasificación manipulada durante el alta
- **WHEN** el cliente incluye categoría, urgencia o prioridad manual en el alta
- **THEN** esos valores no determinan la clasificación y solo se usa la propuesta validada del backend o el resultado «Sin clasificar»

#### Scenario: Edición posterior
- **WHEN** se modifica el título, la descripción, el activo o la clasificación de una incidencia existente
- **THEN** no se llama al proveedor ni se reemplaza automáticamente la clasificación

### Requirement: Catálogo y contrato JSON cerrado
El sistema SHALL aceptar únicamente un objeto JSON con exactamente `category` y `urgency`, ambas cadenas obligatorias no nulas. Las categorías permitidas SHALL ser `hardware`, `software`, `network`, `access`, `other`; las urgencias SHALL ser `low`, `medium`, `high`, `critical`. El backend SHALL validar el resultado contra un JSON Schema cerrado antes de utilizarlo.

#### Scenario: Clasificación en otros
- **WHEN** la respuesta válida contiene `category: other` y una urgencia permitida
- **THEN** se acepta como clasificación completa y se muestra la categoría «Otros»

#### Scenario: Respuesta inválida
- **WHEN** la respuesta está vacía, no es JSON, incluye texto adicional, es un array o contiene claves ausentes o extra, valores nulos, tipos incorrectos o valores ajenos al catálogo
- **THEN** el intento falla sin guardar una clasificación parcial ni asignar valores por defecto

#### Scenario: Instrucciones en la descripción
- **WHEN** la descripción solicita cambiar las instrucciones, el formato o el catálogo
- **THEN** el contrato de validación sigue siendo el mismo y una respuesta que lo incumpla se rechaza

### Requirement: Reintentos limitados
El sistema SHALL realizar un intento inicial y hasta dos reintentos ante errores de conexión, tiempo de espera, HTTP de error, respuesta vacía o incumplimiento del esquema. SHALL detenerse tras el primer resultado válido y limitar cada llamada y cada espera para que el alta tenga un presupuesto temporal finito y documentado.

#### Scenario: Recuperación en segundo intento
- **WHEN** el primer intento falla y el segundo produce un resultado válido
- **THEN** se realizan exactamente dos llamadas y se guarda la clasificación del segundo intento

#### Scenario: Recuperación en tercer intento
- **WHEN** los primeros dos intentos fallan y el tercero produce un resultado válido
- **THEN** se realizan exactamente tres llamadas y se guarda la clasificación del tercero

#### Scenario: Agotamiento
- **WHEN** los tres intentos fallan por errores HTTP, tiempos de espera o respuestas inválidas
- **THEN** no se realiza una cuarta llamada y se completa el alta de una única incidencia «Sin clasificar»

### Requirement: Ausencia de clasificación
Tras agotar los intentos o detectar configuración ausente o inválida, el sistema SHALL conservar el alta con categoría y prioridad nulas y mostrar «Sin clasificar». Esta indicación SHALL ser independiente del estado de atención y no SHALL impedir la consulta ni la edición autorizada. La configuración inválida SHALL evitar llamadas inviables.

#### Scenario: Proveedor no disponible
- **WHEN** se agotan los intentos de clasificación durante un alta válida
- **THEN** se guarda la incidencia en estado propuesta, con categoría y prioridad nulas, consultable como «Sin clasificar»

#### Scenario: Falta de configuración
- **WHEN** falta la API Key o la configuración del proveedor no es válida
- **THEN** se guarda la incidencia «Sin clasificar» sin llamadas externas y se registra un diagnóstico seguro

### Requirement: Corrección manual autorizada
El sistema SHALL permitir a técnicos y administradores editar categoría y urgencia en incidencias no cerradas. Ambos valores SHALL pertenecer al catálogo y formar una clasificación completa; una corrección completa SHALL retirar «Sin clasificar». Los solicitantes no SHALL poder alterar estos campos, incluso mediante peticiones manipuladas.

#### Scenario: Corrección de propuesta
- **WHEN** un técnico o administrador autorizado cambia ambos valores por una combinación válida
- **THEN** se guarda su elección sin llamada al proveedor ni cambios en el seguimiento

#### Scenario: Clasificación tras fallo
- **WHEN** un técnico clasifica una incidencia «Sin clasificar» con categoría y urgencia válidas
- **THEN** se guardan ambos valores y deja de mostrarse «Sin clasificar»

#### Scenario: Corrección parcial
- **WHEN** se intenta establecer categoría sin urgencia o urgencia sin categoría, o vaciar una clasificación completa
- **THEN** se rechaza la modificación sin perder la clasificación anterior

#### Scenario: Solicitante intenta modificar clasificación
- **WHEN** un solicitante envía un cambio de categoría o prioridad en una incidencia propia editable
- **THEN** se rechaza el cambio de clasificación en el backend y se conserva el registro anterior

#### Scenario: Incidencia cerrada
- **WHEN** cualquier usuario intenta modificar la clasificación de una incidencia cerrada
- **THEN** se rechaza la operación sin modificarla

### Requirement: Compatibilidad de incidencias anteriores
El sistema SHALL conservar las prioridades de las incidencias anteriores sin inventar categorías ni llamar al proveedor. Una incidencia anterior sin categoría SHALL mostrarse «Sin clasificar», conservando visible su prioridad histórica. La edición de otros datos SHALL ser posible sin forzar su clasificación; al clasificarla manualmente SHALL exigirse la pareja completa.

#### Scenario: Migración y consulta histórica
- **WHEN** se migra una incidencia anterior con prioridad alta y se consulta
- **THEN** conserva la prioridad alta, no tiene categoría y muestra «Sin clasificar», sin llamadas al proveedor

#### Scenario: Edición de datos históricos
- **WHEN** un usuario autorizado cambia únicamente título, descripción o activo de una incidencia anterior sin categoría
- **THEN** se guarda la edición conservando la prioridad histórica y la ausencia de categoría

### Requirement: Credenciales y transmisión restringida
El sistema SHALL mantener la API Key exclusivamente en el backend y enviar al proveedor únicamente la descripción y las instrucciones y opciones necesarias para clasificar. Credenciales, descripciones completas y respuestas completas no SHALL aparecer en logs ni diagnósticos. Los errores mostrados al usuario no SHALL revelar detalles sensibles.

#### Scenario: Datos enviados al proveedor
- **WHEN** se realiza una llamada de clasificación
- **THEN** el contenido del usuario enviado contiene solo la descripción y excluye título, identidad, comentarios y datos del activo

#### Scenario: Diagnóstico seguro
- **WHEN** un intento tiene éxito o falla
- **THEN** se registra resultado, número de intento e identificador de correlación sin credenciales, descripción completa ni respuesta completa

#### Scenario: Recursos del navegador
- **WHEN** un usuario crea o consulta una incidencia y recibe recursos o errores del panel
- **THEN** no recibe la API Key ni cabeceras de autenticación del proveedor
