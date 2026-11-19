# Arquitectura de SIPE Grupo 5

La entrega parte de SIPE V2, revisión `6e52d42f483ccf3b2fe689c30a092c582924b78f`. El repositorio Grupo 5 comienza con un historial propio. Su primer commit incorpora el árbol versionado de la aplicación. Los archivos locales de ejecución permanecen en sus instalaciones de origen.

La entrada `index.php` delega en `public/index.php`. `app/runtime.php` inicia configuración y sesiones locales. Los controladores seleccionan vistas PHP y llaman a servicios. Los servicios de préstamo, sanciones, reportes, perfiles y autorización conservan sus reglas de SIPE V2. Los estilos, scripts de interfaz, dependencias y archivos visuales proceden de la misma revisión.

## Instalación y datos

En la primera navegación local, `app/runtime.php` llama a `app/setup.php` para cargar la conexión estándar u opcional. `Installer` coordina las peticiones mediante un bloqueo de archivo para la extracción y `GET_LOCK` para el nombre de la base. Valida la propiedad de la base y registra los pasos de esquema. Al completar la configuración, redirige a la ruta de login existente.

`database/exportacion-academica.sql` incorpora las migraciones de origen. El instalador crea las tablas y la vista con las definiciones verificadas por `entrega-plan.json`, importa las sentencias de datos del respaldo en una transacción y compara las filas y secuencias con el corte en `datos-academicos.json`. Antes de confirmar, prepara el hash de la cuenta de evaluación y marca la base como lista. `database/source/` conserva el esquema y las migraciones de procedencia para revisión.

El acceso web se limita a localhost. Apache protege archivos y carpetas internas. La configuración de la instalación y las sesiones se guardan fuera de `htdocs`. Los enlaces, formularios, redirecciones y recursos estáticos toman la ruta base del script de entrada.
