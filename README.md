# SIPE GRUPO 5 — SISTEMA INDEPENDIENTE DE PRÉSTAMOS ESTUDIANTILES

## Guía rápida para el profesor

1. Descargue [SIPE-GRUPO-5-ENTREGA-FINAL.zip](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/download/v1.0.0/SIPE-GRUPO-5-ENTREGA-FINAL.zip) desde [GitHub Releases](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/tag/v1.0.0).
2. Extraiga el ZIP dentro de `C:\xampp\htdocs`. El archivo de entrada debe quedar en `C:\xampp\htdocs\SIPE-grupo-5\index.php`.
3. Inicie **Apache** y **MySQL** en el panel de XAMPP. Abra [SIPE Grupo 5 en localhost](http://localhost/SIPE-grupo-5/).
4. En el asistente, indique una conexión MySQL autorizada y una base nueva, como `sipe_grupo_5`. Si ese nombre ya existe, elija `sipe_grupo_5_evaluacion`. En XAMPP, el puerto habitual es **3306**. Los valores de usuario y contraseña MySQL dependen de su instalación.
5. Cree una contraseña de evaluación de al menos 15 caracteres. Al finalizar, inicie sesión con **`profesor@sipe.local`** y la contraseña que eligió. Las visitas posteriores abren el sistema con la misma base.

## Propósito y alcance

Entrega académica del Grupo 5 para la **Fundación Academia de Dibujo Profesional**. SIPE registra la disponibilidad y los movimientos de recursos institucionales, permite consultar antecedentes de los prestatarios y conserva la trazabilidad de cada operación. Esta edición incorpora la versión verificada de SIPE V2 con una instalación independiente para evaluación.

## Tecnologías y requisitos

Apache y PHP de XAMPP, PHP **8.2 o superior**, MariaDB **10.4 o superior** o un servidor MySQL compatible con el esquema y sus restricciones. La entrega se ensayó con Apache **2.4.65**, PHP **8.3.36** y MariaDB **10.11.16** de XAMPP. Se requieren las extensiones PHP `pdo_mysql`, `mbstring`, `xmlwriter`, `dom`, `session` y `hash`, además de `allow_url_fopen`, `mod_rewrite` y un `.htaccess` activo. El ZIP trae las dependencias de ejecución. El asistente realiza la carga de datos desde el navegador.

## Módulos y reglas de operación

- **Usuarios de préstamos y hoja de vida.** Consulta la ficha por código institucional o QR, incluyendo foto, grupo, programa, vigencia del carné e historial. Los QR se reproducen con los tokens de la base. En la instalación inicial, una ficha usa la imagen genérica porque carece de foto asociada.
- **Autorización académica.** El administrador define las categorías habilitadas por programa. Los estudiantes requieren autorización para la categoría. Docentes y administrativos acceden a las categorías activas según disponibilidad, sanciones y demás controles, con registro directo de la solicitud.
- **Solicitud de Recursos.** Identifica al prestatario, selecciona el tipo y el recurso, revisa el horario disponible y confirma. El préstamo interno propone la devolución a las **8:00 p. m.** del mismo día. El externo requiere fecha y hora de devolución y permite generar un vale después de la entrega.
- **Movimientos e inspección.** Selecciona la reserva para entregar durante el horario previsto. La devolución registra el estado físico y actualiza la disponibilidad. El panel conserva entregas, devoluciones y cancelaciones. El filtro de pendientes históricas sirve para revisar y cerrar cada caso.
- **Sanciones.** El administrador busca por código y selecciona un préstamo del usuario cuando corresponde. Una sanción abierta bloquea nuevas solicitudes, reservas y entregas, y permite devolver recursos ya prestados. Al cerrar todas las sanciones abiertas se recupera el acceso según las reglas vigentes.
- **Usuarios del sistema, reportes y auditoría.** El administrador gestiona cuentas y permisos, consulta reportes y revisa los eventos registrados. Las cuentas del sistema corresponden al personal que opera SIPE.

Consulte el [manual de usuario](documentacion/MANUAL-DE-USUARIO.md) para recorrer los módulos.

## Estructura y arquitectura

| Ubicación | Función |
| --- | --- |
| `index.php` y `public/` | Entrada PHP y despacho de rutas. Se conservan sus nombres por compatibilidad. |
| `app/` | Controladores, servicios, modelos, vistas, instalación y seguridad. |
| `assets/` | Estilos, scripts, logotipo e imagen genérica. |
| `database/` | Esquema, migraciones, plan de instalación, datos y exportación SQL. |
| `vendor/`, `composer.json` y `composer.lock` | Dependencias empaquetadas, declaración y versiones de Composer. |
| `config/`, `var/` y `docs/` | Configuración de referencia, área de ensayos y documentación histórica del proyecto fuente. |
| `scripts/`, `tests/` y `documentacion/` | Herramientas de preparación, pruebas y manuales de la entrega. |

La interfaz llama a controladores PHP, los servicios aplican las reglas de préstamo y PDO ejecuta consultas preparadas en MariaDB. Consulte la [arquitectura](documentacion/ARQUITECTURA.md).

## Base de datos y seguridad

La instalación restaura **546 filas de aplicación** en 15 tablas, incluidas **124 personas**, **150 recursos**, **123 fotografías** y **124 tokens QR**. Se incluyen los registros de préstamos, reservas, sanciones, inspección y auditoría. Las 11 referencias pendientes se documentan aparte. El [informe de datos](documentacion/BASE-DE-DATOS.md) explica el registro adicional respecto al conteo anterior.

El asistente valida el esquema, la conexión local y las protecciones de Apache. Crea una base propia, restaura los datos en una transacción y compara cada fila. La contraseña se genera para la instalación local y su hash sustituye la marca sin credencial de la exportación. Las credenciales SQL y las sesiones quedan fuera de `htdocs`, en la carpeta privada de la instalación. El acceso usa sesiones, permisos, tokens CSRF y auditoría.

## Pruebas, ramas y versión

- **main** contiene la edición verificada de evaluación.
- **desarrollo** reúne la incorporación, la portabilidad y la documentación.
- **entrega/final** conserva el trabajo de armado y verificación del paquete.
- **v1.0.0** identifica el código del ZIP publicado.

Los commits documentan cambios realizados en el repositorio independiente. La [evidencia de pruebas](documentacion/PRUEBAS.md) describe los ensayos sobre bases separadas.

## Solución de errores y reinicio

- **El asistente dice que la base ya existe.** Indique otro nombre que comience por `sipe_grupo_5_`.
- **La conexión SQL es rechazada.** Compruebe MySQL en XAMPP, puerto y credenciales. Use una cuenta SQL con autorización para crear la base, tablas y vistas y operar sobre sus datos.
- **Apache informa que una carpeta interna no está protegida.** Use una instalación con `.htaccess`, `mod_rewrite` y `AllowOverride` habilitados. El asistente conserva su protección hasta que se cumpla ese requisito.
- **Apache tiene otro puerto.** Inclúyalo al abrir la URL de localhost. Para cambiar el puerto SQL, use el campo del asistente.
- **Detener o reiniciar.** Cierre sesión y detenga Apache y MySQL desde XAMPP. Para volver a usar SIPE, inicie los dos servicios y abra la misma URL. La base y la configuración local se conservan.

Los detalles están en la [guía de instalación](documentacion/GUIA-DE-INSTALACION.md).
