# Guía de instalación con XAMPP

## Instalación estándar

1. Descargue [SIPE-GRUPO-5-ENTREGA-FINAL.zip](https://github.com/OnlyWhoo/SIPE-grupo-5/releases/download/v1.0.1/SIPE-GRUPO-5-ENTREGA-FINAL.zip).
2. Extraiga la carpeta `SIPE-grupo-5` en `htdocs`.
3. Inicie Apache y MySQL desde el panel de XAMPP.
4. Abra `http://localhost/SIPE-grupo-5/`. En el primer acceso, espere a que SIPE termine de preparar la base y abra el login.
5. Ingrese con `profesor@sipe.local` y `Sipe2026!`. Esta cuenta es exclusivamente para evaluación académica local.

La URL sigue el nombre de la carpeta extraída. Si Apache usa otro puerto, inclúyalo en la URL del navegador.

## Configuración local opcional

Para un XAMPP con parámetros diferentes, cree `config/config.local.php` dentro de la carpeta extraída antes de iniciar. El archivo devuelve un arreglo PHP. Este ejemplo usa un nombre de base independiente y muestra los demás valores estándar. Puede omitir las entradas que utilicen dichos valores.

```php
<?php
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'sipe_grupo_5_evaluacion',
    'user' => 'root',
    'password' => '',
];
```

Use `sipe_grupo_5` o un nombre que empiece por `sipe_grupo_5_` con sufijos alfanuméricos en minúsculas separados por guiones bajos. La cuenta MariaDB necesita permisos para crear la base, las tablas y la vista, consultar sus metadatos y operar sobre sus datos. Mantenga las credenciales reales en el equipo local. Apache protege la carpeta `config/` y Git ignora el archivo opcional.

## Reanudación y visitas posteriores

La configuración y las sesiones se guardan en `sipe-grupo-5-private`, junto a `htdocs`, en un subdirectorio por ruta de instalación. Reinicie Apache y MySQL y vuelva a abrir la misma URL. El marcador permite reanudar la preparación interrumpida de la misma extracción y conservar los cambios de una instalación lista. Para probar otra extracción en el mismo XAMPP, use un nombre de base independiente en su configuración opcional. Una instalación anterior conserva la cuenta y contraseña con las que se preparó.

Si aparece un diagnóstico de Apache o MariaDB, consulte [README](../README.md) para las comprobaciones básicas.
