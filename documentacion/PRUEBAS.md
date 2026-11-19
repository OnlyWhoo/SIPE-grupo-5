# Pruebas de la entrega

Ensayos del 19 de noviembre de 2026 con Apache 2.4.65, PHP 8.3.36 y MariaDB 10.11.16 de XAMPP. La entrega se extrajo en htdocs y el asistente se completó desde el navegador. Cada instalación de ensayo usó una base independiente.

| Ensayo | Comprobaciones correctas |
| --- | ---: |
| PHP unitarias | 17 |
| Integración | 37 |
| Flujos y reglas | 45 |
| Bloqueo por sanciones | 24 |
| Concurrencia | 5 |
| Instalador | 12 |
| HTTP y cotejo de datos desde el ZIP | 55 |
| Comparación de capturas en Edge | 10 |

## Qué se verificó

- El instalador bloquea bases ajenas y reanuda interrupciones sin duplicar la siembra. Se probó una cuenta SQL sin permiso CREATE.
- Las 546 filas de las 15 tablas de aplicación coincidieron entre el respaldo restaurado, la exportación SQL y la base instalada por el asistente, con el hash de acceso normalizado para comparar. Se cotejaron claves, estados, fotos binarias, asociaciones y QR.
- La prueba HTTP cubrió login con contraseña creada localmente, protección de los archivos internos, búsqueda por código y QR, hoja de vida, reservas, horarios, faltas de autorización, docente y administrativo sin aprobación individual, entrega, devolución, inspección, cancelación, vales, sanciones y auditoría.
- Edge comparó a nivel de píxel el inicio de sesión, el panel, los préstamos, la identificación, el bloqueo, la hoja de vida, sanciones, reportes, el vale y la vista móvil de bloqueo con una extracción aislada del código fuente. Las diez capturas coincidieron.
- Los ensayos de concurrencia verificaron reserva y entrega simultáneas, así como el orden entre una sanción abierta y una operación de préstamo.

## Reproducción para desarrollo

Los scripts PHP de la carpeta tests ejecutan los casos unitarios, de integración, de flujo, de sanciones, concurrencia y del instalador. Ejecute integration.php antes de workflow.php, sanction-block.php y concurrency.php, en ese orden, con su MariaDB de XAMPP. Los scripts crean bases de ensayo. Use tests/verificar-copia.php para comparar una restauración recién creada y tests/entrega-web.py para probar una extracción limpia. Estas herramientas pertenecen al repositorio de desarrollo. La evaluación se realiza desde el ZIP y el navegador.
