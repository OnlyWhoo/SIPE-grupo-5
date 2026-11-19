# Guía de instalación con XAMPP

## Primera visita

1. Descargue el ZIP de la [versión académica](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/tag/v1.0.0). Puede comparar su SHA-256 con el publicado en Releases.
2. Extraiga el ZIP directamente en \`htdocs\` de XAMPP. Abra \`http://localhost/SIPE-grupo-5/\` después de iniciar Apache y MySQL.
3. El asistente muestra la conexión local. Use el puerto SQL de su XAMPP y las credenciales de una cuenta autorizada. En una instalación con el puerto habitual, use \`3306\`.
4. Elija una base nueva \`sipe_grupo_5\` o con un sufijo. El instalador comprueba su propiedad antes de reanudar una instalación interrumpida.
5. Seleccione una contraseña de evaluación de entre 15 caracteres y 72 bytes y pulse **Preparar y continuar**. El proceso valida la configuración, crea la base, verifica el esquema y restaura los datos.
6. Ingrese con \`profesor@sipe.local\` y la contraseña que eligió en el paso anterior.

## Permisos y reanudación

La cuenta SQL autorizada necesita crear la base nueva, tablas y vistas, consultar el esquema en \`information_schema\` y ejecutar las operaciones de SIPE. Si la cuenta carece de permiso de creación, el asistente solicita una cuenta autorizada. Puede usar un sufijo diferente cuando el nombre está ocupado.

Para reanudar una primera instalación interrumpida, use el mismo nombre, conexión y contraseña de evaluación. La configuración vive fuera de \`htdocs\` bajo \`sipe-grupo-5-private\`. Cada directorio instalado tiene su propia carpeta privada identificada por la ruta. Si cambia de carpeta o necesita reinstalar, elija una base nueva para la nueva instalación.

## Cuando el sistema ya está instalado

Cierre sesión antes de detener Apache o MySQL desde XAMPP. Para reiniciar, vuelva a iniciar los servicios y abra la misma URL. Para cuestiones de protección de directorios o extensiones de PHP, consulte la sección de solución de errores del [README](../README.md).
