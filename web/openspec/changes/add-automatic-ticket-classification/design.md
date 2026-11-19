# Design

## Context

Ver [proposal.md](proposal.md) para la motivación. `CreateTicket::handle` autoriza, valida y guarda dentro de una transacción; `TicketData` exige prioridad y comprueba el activo con bloqueo compartido. `UpdateTicket` vuelve a cargar y bloquear el registro antes de autorizar. `TicketPolicy` permite al solicitante editar datos básicos de sus incidencias en propuesta o abierta; las cerradas son inmutables.

La columna `priority` es obligatoria y tiene valor predeterminado `medium`; no existe categoría. Formularios, tablas y ficha usan el enum `TicketPriority`. Las pruebas actuales asumen prioridad manual y deben actualizarse expresamente para el nuevo contrato. No existe dependencia declarada de JSON Schema en `composer.json`.

## Goals / Non-Goals

**Goals:** separar llamadas remotas de transacciones, compartir el contrato del proveedor con su validador, preservar datos históricos y proteger la clasificación con autorización del backend.

**Non-Goals:** colas, reclasificación al editar, historial de propuestas y garantías de idempotencia entre peticiones de alta independientes. Los reintentos del clasificador pertenecen a una sola operación de alta y no crean registros adicionales.

## Decisions

### 1. Clasificación síncrona antes del guardado

`CreateTicket` seguirá siendo la entrada común. Autorizará y validará título, descripción y referencia opcional de activo antes de clasificar. La validación previa comprobará existencia sin abrir transacciones ni adquirir bloqueos. Descartará los campos de clasificación suministrados por el cliente en el alta.

Un servicio `TicketClassifier` devolverá una propuesta completa validada o un resultado sin clasificación. Después se abrirá una transacción breve que repetirá la autorización y comprobará la existencia del activo con bloqueo compartido antes de insertar una única incidencia. Si el activo desaparece durante la llamada, se rechazará el guardado con el error de validación existente, sin registro parcial. Fallos de persistencia no se convertirán en fallos del LLM ni provocarán más llamadas.

Separar en `TicketData` la validación de datos básicos, la comprobación del activo bajo transacción y la validación de edición de clasificación permite reutilizar reglas sin conservar bloqueos durante esperas remotas. Verificar también que la página Filament no envuelve esta acción en una transacción exterior.

Alternativas: una cola requeriría estados pendientes, procesamiento posterior y protección frente a una corrección técnica concurrente; no se necesita para el alcance actual. Una llamada dentro de la transacción prolongaría bloqueos.

### 2. Representación mínima y compatible

Crear `TicketCategory` con valores `hardware`, `software`, `network`, `access`, `other` y etiquetas Hardware, Software, Red, Accesos y Otros. Las cinco categorías fueron confirmadas por el usuario durante la propuesta. Mantener `TicketPriority` para urgencia; no añadir una columna `urgency`.

Añadir `category` nullable y permitir `priority` nullable, retirando su valor predeterminado. Una nueva incidencia sin clasificación tendrá ambas columnas nulas. La clasificación estará completa únicamente cuando ambos valores existan; su ausencia se mostrará como «Sin clasificar», separada de `TicketStatus`. No hace falta almacenar un estado de clasificación redundante ni un valor de enum ficticio.

Las incidencias anteriores conservarán prioridad y tendrán categoría nula. Esta combinación se admite exclusivamente como dato histórico conservado: puede editarse título, descripción o activo sin completarla. No realizar backfill ni llamadas masivas. Una modificación real de clasificación debe proporcionar una pareja completa; guardar los datos básicos con los mismos valores históricos no se interpreta como reclasificar. No permitir vaciar una clasificación completa.

Actualizar casts, factories, seeders, tablas, filtros y ficha para tolerar nulos. El filtro de prioridad alta seguirá incluyendo registros históricos con esa prioridad. No se añade un filtro nuevo como requisito de este cambio.

Alternativa: sustituir prioridades históricas por nulos perdería decisiones previas; añadir «Sin clasificar» al estado de atención alteraría el seguimiento.

### 3. Adaptador OpenRouter y contrato único

Usar el cliente HTTP del backend mediante un adaptador `OpenRouterTicketClassifier`, sin SDK de OpenAI ni claves de ese proveedor. Configurar un endpoint fijo HTTPS de OpenRouter para Chat Completions. Una instrucción de sistema delimitará el catálogo y la tarea; la descripción validada se enviará como contenido de usuario sin título, identidad, comentarios ni datos del activo. No habilitar herramientas ni streaming.

`OPENROUTER_MODEL` tendrá como valor lógico `gpt-oss-120b`. El adaptador resolverá explícitamente ese alias al identificador de proveedor `openai/gpt-oss-120b`; confirmar ese identificador y sus parámetros en los metadatos públicos del modelo durante la tarea de integración, sin llamadas de inferencia ni credenciales. No cambiar de modelo silenciosamente si no hay soporte.

Solicitar `response_format.type = json_schema`, nombre `ticket_classification`, `strict = true` y `provider.require_parameters = true`. Leer el contenido de la respuesta del proveedor y decodificarlo como objeto, preservando la diferencia entre objetos y arrays. Respuestas sin contenido, truncadas o con error se consideran fallos. Limitar el tamaño de respuesta procesada y el presupuesto de salida para esta pareja de valores.

Un componente `TicketClassificationSchema` construirá el esquema del issue 7 a partir de los enums: objeto, `category` y `urgency` obligatorias, ambas cadenas con sus enums, sin propiedades extra. Este mismo esquema se enviará y se usará localmente. Proponer `opis/json-schema` como validador real de JSON Schema, verificando compatibilidad con PHP 8.3 y el borrador 2020-12 antes de fijar su versión en Composer. No sustituir el esquema por comprobaciones parciales de campos.

Solo tras validar se convertirá `urgency` en `TicketPriority` y `category` en `TicketCategory`. No retirar bloques Markdown ni intentar reparar respuestas inválidas.

Fuentes consultadas mediante `find-docs` el 18 de noviembre de 2026:

- [Structured Outputs](https://openrouter.ai/docs/guides/features/structured-outputs): contrato `json_schema` y selección de proveedores compatibles.
- [Compatibility](https://openrouter.ai/docs/guides/features/compatibility): `require_parameters` evita proveedores que omiten parámetros; no sustituye la validación local.

Alternativa: confiar en `strict` del proveedor no protege frente a respuestas defectuosas. Un SDK adicional no aporta una necesidad para esta petición HTTP acotada.

### 4. Un solo responsable de reintentos

El servicio realizará un bucle de máximo tres intentos que incluye petición, lectura, decodificación y validación. Desactivar reintentos adicionales del cliente HTTP y no envolver la creación del registro en ese bucle. Reintentar tanto errores de transporte y HTTP como contenido inválido, incluso errores HTTP permanentes, para respetar el contrato de tres intentos del issue. Detenerse al primer éxito.

Valores propuestos de configuración del servidor:

| Variable | Valor predeterminado | Uso |
| --- | --- | --- |
| `OPENROUTER_API_KEY` | vacío | Credencial; vacío desactiva llamadas y produce «Sin clasificar» |
| `OPENROUTER_MODEL` | `gpt-oss-120b` | Modelo lógico con resolución explícita descrita arriba |
| `OPENROUTER_TIMEOUT_SECONDS` | `10` | Tiempo máximo total por llamada |
| `OPENROUTER_CONNECT_TIMEOUT_SECONDS` | `3` | Conexión, dentro del límite total |
| `OPENROUTER_RETRY_DELAY_MS` | `250` | Espera entre intentos, como máximo dos esperas |

Validar que los tiempos sean positivos, que conexión no supere el total y que la espera sea finita y no negativa. Los valores predeterminados dan un presupuesto remoto de 30,5 segundos más procesamiento y persistencia. Alinear los límites del servidor HTTP con ese presupuesto. La configuración inválida se detectará antes del bucle y producirá un diagnóstico seguro sin llamadas. Las esperas serán sustituibles en pruebas.

Alternativa: reintentar solo errores HTTP transitorios ahorraría solicitudes, pero dejaría fuera los errores de esquema exigidos y cambiaría el comportamiento acordado.

### 5. Autorización y formularios

Añadir una capacidad de política para editar clasificación, permitida a técnicos y administradores de incidencias no cerradas. Dentro del bloqueo de `UpdateTicket`, diferenciar los datos básicos de los cambios reales de categoría y prioridad; autorizar y validar estos últimos antes de guardar nada. Una petición del solicitante que altere clasificación se rechazará íntegramente. No basta con ocultar campos.

Retirar categoría y prioridad de la creación para cualquier rol. En edición, mostrar la pareja solo al personal autorizado. Permitir conservar una incidencia sin clasificar al editar únicamente datos básicos, pero exigir ambos valores si se inicia su clasificación. Mantener los límites de estado de la política actual. Mostrar «Urgencia» con aclaración de su relación con la prioridad y conservar las cuatro etiquetas existentes. Ficha y listado mostrarán categoría, urgencia y «Sin clasificar» según corresponda; la prioridad histórica continuará visible.

Alternativa: dejar que cualquier editor cambie prioridad incumpliría la restricción del issue y la revisión por técnicos.

### 6. Diagnósticos sin contenido sensible

Generar un identificador de correlación por operación, antes de tener identificador de incidencia. Registrar intento, resultado, código de causa controlado y, cuando proceda, código HTTP. No registrar excepciones completas del cliente HTTP, cabeceras, cuerpos de solicitud o respuesta, descripción ni API Key. Registrar por separado configuración inválida y fallo definitivo con el mismo identificador.

Obtener secretos solo desde configuración del servidor. Documentar variables sin valores reales en `.env.example`; no leer ni modificar `.env`. Las pruebas usarán credenciales ficticias, cliente simulado y prohibición de solicitudes externas inesperadas.

## Risks / Trade-offs

- Latencia del alta de hasta unos 30,5 segundos de trabajo remoto → límites explícitos, sin bloqueos durante esperas y documentación del presupuesto HTTP; reevaluar colas si el uso real lo exige.
- JSON válido con clasificación semánticamente incorrecta → propuesta visible y editable por personal técnico; la validación no garantiza precisión del modelo.
- Cambios de disponibilidad o soporte del proveedor → verificar metadatos, exigir parámetros y terminar en «Sin clasificar» sin sustituir el modelo.
- La descripción puede contener datos sensibles escritos por el usuario → enviar solo ese campo y documentar su transmisión a OpenRouter; excluir su contenido de logs.
- Prioridad nula rompe supuestos anteriores → adaptar casts, vistas, filtros y pruebas de integración; probar conservación histórica en SQLite y PostgreSQL desechables.
- Reversión al esquema anterior no puede representar prioridades nulas → exigir preparación explícita de datos y copia de seguridad, sin rellenar prioridades automáticamente.

## Migration Plan

1. Añadir dependencia de validación y configuración sin activar llamadas de inferencia reales en desarrollo o pruebas.
2. En una ventana de despliegue coordinada, añadir categoría nullable y hacer prioridad nullable sin valor predeterminado; conservar todos los valores existentes. Evitar mezclar versiones de la aplicación durante la migración.
3. Desplegar servicio, acciones y vistas compatibles. Configurar la credencial mediante el mecanismo habitual de secretos del servidor fuera de este cambio; sin credencial el alta sigue funcionando «Sin clasificar».
4. Ejecutar pruebas aisladas de migración y regresión; comprobar la interfaz con proveedor simulado y roles solicitante, técnico y administrador.
5. Para revertir, detener escrituras y conservar una copia completa de la base. Antes de desplegar el código anterior o revertir la migración, resolver explícitamente todas las prioridades nulas mediante decisiones técnicas y verificar la compatibilidad con ese código. El rollback de esquema debe abortar con un mensaje claro si quedan nulos y advertir de la pérdida de la columna de categoría. No asignar prioridades ficticias ni eliminar incidencias para completar la reversión.
