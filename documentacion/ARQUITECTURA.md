# Arquitectura de SIPE Grupo 5

La entrega parte de SIPE V2, revisión \`6e52d42f483ccf3b2fe689c30a092c582924b78f\`. El repositorio Grupo 5 comienza con un historial propio. Su primer commit incorpora el árbol versionado de la aplicación. Los archivos locales de ejecución permanecen en sus instalaciones de origen.

La entrada \`index.php\` delega en \`public/index.php\`. \`app/runtime.php\` inicia configuración y sesiones locales. Los controladores seleccionan vistas PHP y llaman a servicios. Los servicios de préstamo, sanciones, reportes, perfiles y autorización conservan sus reglas de SIPE V2. Los estilos, scripts de interfaz, dependencias y archivos visuales proceden de la misma revisión.

## Instalación y datos

\`app/setup.php\` presenta el asistente de conexión y contraseña. \`Installer\` crea y verifica las tablas y la vista a partir de \`database/entrega-plan.json\`. \`DatosAcademicos\` carga \`database/datos-academicos.json\` con claves foráneas activas, reconstruye las fotografías binarias e instala una clave nueva para la cuenta de evaluación. Antes de cerrar la transacción, compara las filas restauradas con el conjunto distribuido.

\`database/source/\` conserva el esquema y las migraciones de procedencia. \`database/exportacion-academica.sql\` contiene DDL y datos para revisión técnica. El instalador web usa la representación JSON y aplica sus verificaciones de propiedad y esquema. Las tablas de propiedad de instalación registran el estado local y se crean en el equipo del profesor.

El servidor admite acceso local. Apache protege las carpetas internas. Las credenciales y sesiones locales se guardan fuera de \`htdocs\`. Las rutas internas se derivan de la carpeta de entrada. El directorio técnico \`vendor\`, los nombres SQL y los namespaces se conservan como dependencias de la aplicación.
