# Gestión de usuarios

Solo los administradores pueden consultar y mantener usuarios desde el panel. El listado muestra nombre, correo y rol. Técnicos y solicitantes no pueden acceder a esta sección, tampoco mediante su URL.

Al crear una cuenta se requieren nombre (hasta 255 caracteres), correo válido único (hasta 255), un rol existente y una contraseña de al menos 12 caracteres. El nombre y correo se recortan y el correo se guarda en minúsculas. En edición, dejar vacía la contraseña conserva la anterior. Las contraseñas se almacenan mediante hash y nunca se recuperan en el formulario.

No se permite eliminar usuarios referenciados como solicitantes, técnicos asignados, autores de comentarios o autores del historial, incluso en incidencias cerradas. Tampoco se puede eliminar ni cambiar el rol del último administrador. Estas garantías se comprueban dentro de una transacción, también para operaciones simultáneas.

Antes de cambiar el rol de un técnico hay que reasignar o cerrar sus incidencias no cerradas. Las asignaciones de incidencias cerradas se conservan como referencia histórica. Un administrador puede cambiar su propio rol si queda otro administrador; sus permisos cambian en la siguiente petición.

La migración normaliza los correos existentes tras comprobar que no colisionan. Si encuentra dos cuentas cuyo correo normalizado coincide, se detiene sin modificar cuentas. La resolución de esas cuentas debe acordarse antes de volver a migrar. No ejecutar recreaciones de la base local para evitar esa comprobación.

Las invitaciones, recuperación de acceso y edición independiente del perfil están fuera de este cambio.
