# Pruebas de SIPE Grupo 5 v1.0.1

Ensayos del 19 de noviembre de 2026 en Windows con Apache 2.4.65, PHP 8.3.36 y MariaDB 10.11.16 de XAMPP. Las pruebas de escritura se ejecutaron sobre bases independientes de SIPE Grupo 5. El cotejo de SIPE V2 se ejecutó con una instantánea de solo lectura.

| Prueba | Resultado |
| --- | --- |
| **A. Instalación limpia** | Una extracción bajo `htdocs`, con XAMPP en el puerto SQL 3306 y sin archivo de configuración local, creó `sipe_grupo_5`. Dos primeras visitas simultáneas llegaron al login. La cuenta de evaluación abrió el panel. |
| **B. Segundo inicio** | Se recargó la aplicación, se cerró Edge y se reiniciaron procesos aislados de Apache y MariaDB de XAMPP. En una sesión nueva de Edge se abrió el login. El estado, identificador de instalación, hash de evaluación y conteos de fichas, reservas, préstamos, sanciones, fotografías y auditoría coincidieron antes y después del reinicio. |
| **C. Conservación de datos** | La restauración coincidió fila a fila con el corte confirmado de la base activa `sipe_v2`. Se verificaron 546 filas en 15 tablas, la vista, claves foráneas, secuencias, 123 fotografías binarias y 124 QR. La cuenta ya existía y conserva su ID, correo y rol. Su hash es propio de la copia. Las tablas técnicas guardan la identidad de instalación independiente. |
| **D. Portabilidad** | Se ensayó una extracción en el subdirectorio `Proyecto final 5`. Se verificaron las rutas CSS, JavaScript y logotipo, así como el login, el cierre de sesión, los enlaces de fichas, las fotografías, los QR y el vale servido por Apache. |
| **E. Seguridad y errores** | Se rechazaron accesos a archivos internos, el POST del asistente anterior y peticiones con host u origen no autorizados. Una segunda extracción que apuntaba a una base ocupada recibió 503 y los datos quedaron intactos. Con MariaDB apagado o el SQL alterado se mostró un diagnóstico y se rechazó la instalación. Se probaron una base vacía preexistente, una cuenta sin CREATE y siete interrupciones recuperables. |
| **F. Funcionalidad** | Se probaron identificación por código y QR, hoja de vida, inventario, autorizaciones, reservas, entregas, vales, devoluciones, inspecciones, cancelaciones, sanciones, reportes y auditoría. Se verificó que los estudiantes siguen limitados por su autorización, que docentes y administrativos reservan sin aprobación individual y que una sanción abierta bloquea nuevas operaciones de préstamo. |

## Pruebas de desarrollo

| Suite | Casos sin fallos |
| --- | ---: |
| Unitarias | 17 |
| Integración | 37 |
| Flujos y reglas | 45 |
| Bloqueo por sanciones | 24 |
| Concurrencia de operaciones | 5 |
| Instalador y reanudación | 15 |

Desde el repositorio de desarrollo, ejecute con PHP de XAMPP los archivos en `tests/`. Ejecute `integration.php` antes de `workflow.php`, `sanction-block.php` y `concurrency.php`, en ese orden. Para una extracción nueva de ensayo en XAMPP, `tests/entrega-web.py` prueba las rutas HTTP y llama a `tests/verificar-copia.php` para cotejar la restauración antes de que el login añada eventos a auditoría.
