# SIPE GRUPO 5 — INSTALACIÓN RÁPIDA

1. Descargue [SIPE-GRUPO-5-ENTREGA-FINAL.zip](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/download/v1.0.1/SIPE-GRUPO-5-ENTREGA-FINAL.zip).
2. Descomprima la carpeta `SIPE-grupo-5` dentro de `htdocs` de XAMPP.
3. Abra el panel de XAMPP.
4. Inicie **Apache** y **MySQL**.
5. Abra `http://localhost/SIPE-grupo-5/` en el navegador.
6. Inicie sesión con el correo **`profesor@sipe.local`** y la contraseña **`Sipe2026!`**.

En el primer acceso, SIPE prepara su base automáticamente con la configuración estándar de XAMPP y abre el inicio de sesión. Las credenciales son exclusivamente para evaluación académica local. Si cambia el nombre de la carpeta antes de instalar, use ese nombre en la URL.

## Tecnología

SIPE Grupo 5 es la entrega académica del Sistema Independiente de Préstamos Estudiantiles para la Fundación Academia de Dibujo Profesional. Usa Apache, PHP 8.2 o superior y MariaDB de XAMPP. El ZIP incluye las dependencias PHP. El entorno probado fue Apache 2.4.65, PHP 8.3.36 y MariaDB 10.11.16.

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

## Base e inicialización

SIPE se conecta mediante PDO a `127.0.0.1:3306` como `root` con contraseña de base de datos vacía. En una instalación nueva crea `sipe_grupo_5` o usa una base de ese nombre si está vacía. Si encuentra una base ocupada de procedencia desconocida, muestra un diagnóstico y conserva su contenido.

El instalador restaura `database/exportacion-academica.sql`, un respaldo lógico de la base activa `sipe_v2` obtenido en una transacción de solo lectura. Incluye **546 filas en 15 tablas**, una vista y las secuencias de identificadores. Los **123 archivos de fotografía** están incluidos como contenido binario dentro del respaldo y mantienen su vínculo con cada prestatario. El esquema ya incluye las migraciones del origen. Consulte el [informe de datos](documentacion/BASE-DE-DATOS.md).

SIPE prepara la cuenta de evaluación existente con `password_hash()` y verifica las filas, el esquema y las secuencias antes de marcar la instalación como lista. Los bloqueos de archivo y servidor coordinan accesos simultáneos. Las visitas posteriores comprueban el marcador y usan la configuración guardada fuera de `htdocs`.

## Instalaciones no estándar y errores

- **MariaDB no responde.** Compruebe que MySQL esté iniciado, que el puerto y la cuenta sean correctos y que la cuenta pueda crear la base, tablas y vistas.
- **Su conexión es distinta o la base está ocupada.** Antes del primer acceso correcto puede crear `config/config.local.php` con la configuración local. Los campos omitidos usan los valores estándar. Consulte el ejemplo en la [guía de instalación](documentacion/GUIA-DE-INSTALACION.md).
- **Apache no protege una carpeta.** Compruebe que extrajo `.htaccess` y que Apache aplica `AllowOverride All` y tiene `mod_rewrite` activo.
- **La preparación se interrumpió.** Inicie los servicios y vuelva a abrir la misma URL. Conserve la carpeta privada y la base para que SIPE compruebe su propiedad y reanude la preparación.
- **La aplicación ya está instalada.** Reinicie los servicios y abra la misma URL. Una extracción en otra carpeta tiene configuración privada propia. Use una base independiente si desea conservar ambas instalaciones.

## Pruebas y publicación

La versión correctiva es `v1.0.1`. El ZIP se construye desde el commit publicado e incluye un manifiesto SHA-256 de sus archivos. El SHA-256 del ZIP se publica por separado en la [versión de GitHub](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/tag/v1.0.1).

Consulte los resultados de instalación, reinicio, cotejo de datos, portabilidad, seguridad y reglas de negocio en [Pruebas de la entrega](documentacion/PRUEBAS.md).

**Evidencias de pruebas.** El proyecto cuenta con un paquete de evidencias de pruebas funcionales, unitarias, de integración, concurrencia y validación del sistema.

[Descargar evidencias de pruebas — SIPE-PRUEBAS.zip](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/download/v1.0.1/SIPE-PRUEBAS.zip)
