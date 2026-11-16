# Sedes y activos

Los tres roles pueden consultar el catálogo de sedes y activos. Solo los administradores pueden crearlos, modificarlos o eliminarlos. El catálogo no muestra incidencias relacionadas ni permite saltarse su visibilidad.

Cada registro requiere código único de hasta 50 caracteres y nombre de hasta 200. Se retiran espacios exteriores y los códigos se guardan en mayúsculas. Los nombres pueden repetirse. Cada activo debe pertenecer a una sede existente; el listado y la ficha muestran su sede.

No se puede eliminar una sede con activos ni un activo relacionado con incidencias, aunque estén cerradas. Para eliminar una sede hay que resolver primero sus referencias sin borrar datos relacionados automáticamente. Cambiar el nombre, código o sede de un activo actualiza el contexto mostrado por sus incidencias; no se mantiene una instantánea histórica de ubicación.

## Activos en incidencias

En el alta se puede buscar un activo por código o nombre y reconocer su sede en la etiqueta. La selección es opcional: si el problema aún no está catalogado, se puede registrar la incidencia sin activo. Las incidencias anteriores mantienen sus datos y muestran «Sin activo asociado».

El solicitante puede sustituir o retirar el activo de sus incidencias en propuesta o abierta. Técnicos y administradores pueden hacerlo en cualquier incidencia no cerrada. La ficha muestra código, nombre y sede actuales, o indica su ausencia. Editar otro campo sin enviar la selección conserva el activo actual; vaciar expresamente la selección lo retira.

Seleccionar un activo inexistente, o eliminado mientras se preparaba el formulario, rechaza la operación sin guardar una incidencia parcial. Los cambios de activo no modifican el estado, asignación, historial ni comentarios. Una incidencia cerrada conserva su asociación sin permitir editarla.

Las migraciones incorporan el catálogo vacío y una referencia opcional, sin inventar asociaciones para registros previos. Antes de revertir su esquema en un entorno con datos útiles, detener escrituras y exportar catálogo y asociaciones: la reversión elimina el contexto incorporado.
