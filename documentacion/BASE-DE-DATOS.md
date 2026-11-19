# Datos de la entrega académica

El conjunto se extrajo de un respaldo lógico consistente de la base activa de SIPE V2. Se restauró en una base aislada de MariaDB de XAMPP para exportar las filas de aplicación. SQL y JSON representan el mismo corte. Las credenciales del sistema de origen se sustituyeron por una marca sin acceso, y la instalación genera el hash de la contraseña que el profesor elige.

| Contenido | Filas restauradas |
| --- | ---: |
| Roles del personal | 2 |
| Cuentas del personal | 1 |
| Programas | 7 |
| Prestatarios | 124 |
| Categorías | 41 |
| Recursos | 150 |
| Autorizaciones académicas | 0 |
| Reservas atendidas con aprobación histórica | 3 |
| Préstamos y entregas | 4 |
| Devoluciones | 1 |
| Inspecciones | 5 |
| Sanciones | 1 |
| Eventos de auditoría | 83 |
| Configuraciones funcionales | 1 |
| Fotografías asociadas | 123 |
| **Total de filas de aplicación** | **546** |

Hay **84 estudiantes**, **21 docentes** y **19 administrativos**. El registro adicional frente al informe anterior corresponde a un alta administrativa posterior a la importación, registrada en la auditoría del 19 de noviembre de 2026. Esa ficha conserva su QR y usa la fotografía genérica. Los 123 pares originales de fotografía y prestatario coincidieron con el conjunto inicial, y se verificaron **124 tokens QR válidos y únicos**.

Tres préstamos estaban activos y uno devuelto en el corte. El inventario conservó sus 150 referencias operativas, 147 disponibles y tres prestadas. Las [11 referencias pendientes](../database/referencias-pendientes.json) proceden de la entrega de inventario y se conservan fuera del catálogo operativo. La sanción existente estaba abierta. La ausencia de autorizaciones académicas refleja el estado de la base de origen. Para probar un préstamo estudiantil, el evaluador puede autorizar una categoría desde el módulo administrativo.

Las tablas técnicas de instalación se crean con un identificador propio. Las columnas generadas se reconstruyen con el esquema. Los identificadores y claves foráneas de los registros de aplicación se conservan.
