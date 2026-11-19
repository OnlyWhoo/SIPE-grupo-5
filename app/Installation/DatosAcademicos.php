<?php
declare(strict_types=1);

namespace Sipe\Installation;

use PDO;
use RuntimeException;

final class DatosAcademicos
{
    public const MARCA_SIN_CLAVE = '!ACCESO-PENDIENTE-DE-CONFIGURACION';

    public static function columnas(array $tabla): array
    {
        return array_values(array_map(
            static fn(array $columna): string => $columna[0],
            array_filter($tabla['signature']['columns'], static fn(array $columna): bool => $columna[5] === null),
        ));
    }

    public static function identificar(string $columna): string
    {
        if (!preg_match('/\A[a-z_][a-z0-9_]*\z/D', $columna)) {
            throw new RuntimeException('Identificador inesperado en el esquema.');
        }
        return chr(96).$columna.chr(96);
    }

    public static function capturar(PDO $db, array $plan): array
    {
        $tablas = [];
        foreach ($plan['tables'] as $nombre => $tabla) {
            $columnas = self::columnas($tabla);
            $campos = array_map(self::identificar(...), $columnas);
            if ($nombre === 'usuarios') {
                // La contraseña del equipo de origen queda fuera de la consulta.
                $campos[array_search('password_hash', $columnas, true)] = "'".self::MARCA_SIN_CLAVE."' AS password_hash";
            }
            $primaria = array_column($tabla['signature']['indexes']['PRIMARY']['columns'], 0);
            $orden = implode(',', array_map(self::identificar(...), $primaria));
            $consulta = $db->query('SELECT '.implode(',', $campos).' FROM '.self::identificar($nombre)." ORDER BY $orden");
            $binario = $nombre === 'fotografias_prestatarios' ? array_search('contenido', $columnas, true) : null;
            $filas = [];
            while ($fila = $consulta->fetch(PDO::FETCH_NUM)) {
                if ($binario !== null) {
                    $fila[$binario] = base64_encode($fila[$binario]);
                }
                $filas[] = array_map(static fn(mixed $valor): ?string => $valor === null ? null : (string)$valor, $fila);
            }
            $tablas[$nombre] = ['columnas'=>$columnas, 'filas'=>$filas];
        }
        return $tablas;
    }

    public static function leer(string $carpeta): array
    {
        $datos = Entrega::read($carpeta.'/datos-academicos.json');
        if (($datos['version'] ?? null) !== 1 || !is_array($datos['tablas'] ?? null)
            || !is_array($datos['secuencias'] ?? null)
            || !is_array($datos['administrador'] ?? null)
            || !is_string($datos['administrador']['correo'] ?? null)
            || !is_string($datos['administrador']['id'] ?? null)) {
            throw new RuntimeException('El archivo de datos académicos tiene un formato incompatible.');
        }
        return $datos;
    }

    public static function secuencias(PDO $db, string $base): array
    {
        $consulta = $db->prepare('SELECT TABLE_NAME, AUTO_INCREMENT FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=? AND AUTO_INCREMENT IS NOT NULL ORDER BY TABLE_NAME');
        $consulta->execute([$base]);
        return array_map('strval', $consulta->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    public static function ddl(string $nombre, array $tabla, array $datos): string
    {
        $secuencia = $datos['secuencias'][$nombre] ?? null;
        if ($secuencia === null) {
            return $tabla['sql'];
        }
        if (!is_string($secuencia) || !preg_match('/\A[1-9][0-9]{0,19}\z/D', $secuencia)) {
            throw new RuntimeException('Secuencia inválida para '.$nombre.'.');
        }
        return $tabla['sql'].' AUTO_INCREMENT='.$secuencia;
    }

    public static function sql(array $plan, array $datos): string
    {
        if (array_keys($plan['tables']) !== array_keys($datos['tablas'])) {
            throw new RuntimeException('Las tablas del respaldo no coinciden con el esquema.');
        }
        $sql = "-- SIPE Grupo 5: respaldo de las tablas y la vista de aplicación de SIPE V2.\nSET NAMES utf8mb4;\n";
        foreach ($plan['tables'] as $nombre => $tabla) {
            $sql .= self::ddl($nombre, $tabla, $datos).";\n\n";
        }
        $sql .= $plan['view']['sql'].";\n\nSTART TRANSACTION;\n";
        foreach ($plan['tables'] as $nombre => $tabla) {
            $columnas = self::columnas($tabla);
            if ($datos['tablas'][$nombre]['columnas'] !== $columnas) {
                throw new RuntimeException('Las columnas del respaldo no coinciden en '.$nombre.'.');
            }
            $campos = implode(',', array_map(self::identificar(...), $columnas));
            $prefijo = 'INSERT INTO '.self::identificar($nombre)." ($campos) VALUES (";
            foreach ($datos['tablas'][$nombre]['filas'] as $fila) {
                if (!is_array($fila) || !array_is_list($fila) || count($fila) !== count($columnas)) {
                    throw new RuntimeException('Una fila académica tiene columnas inesperadas.');
                }
                $literales = [];
                foreach ($fila as $i => $valor) {
                    $columna = $columnas[$i];
                    if ($nombre === 'usuarios' && $columna === 'password_hash' && $valor !== self::MARCA_SIN_CLAVE) {
                        throw new RuntimeException('La exportación no admite contraseñas de origen.');
                    }
                    if ($nombre === 'fotografias_prestatarios' && $columna === 'contenido') {
                        $foto = base64_decode($valor, true);
                        if ($foto === false || (@getimagesizefromstring($foto)['mime'] ?? null) !== $fila[array_search('mime', $columnas, true)]) {
                            throw new RuntimeException('Una fotografía académica no es válida.');
                        }
                        $literales[] = "X'".bin2hex($foto)."'";
                    } else {
                        $literales[] = $valor === null ? 'NULL' : "CONVERT(X'".bin2hex($valor)."' USING utf8mb4)";
                    }
                }
                $sql .= $prefijo.implode(',', $literales).");\n";
            }
        }
        return $sql."COMMIT;\n";
    }

    public static function respaldo(string $carpeta, array $plan, array $datos): array
    {
        $archivo = $carpeta.'/exportacion-academica.sql';
        $sql = is_file($archivo) ? file_get_contents($archivo) : false;
        if ($sql === false) {
            throw new RuntimeException('Falta el respaldo SQL. Extrae nuevamente el ZIP de SIPE Grupo 5.');
        }
        $sql = str_replace("\r\n", "\n", $sql);
        // Solo se aceptan las sentencias del corte verificado, nunca SQL proporcionado por HTTP.
        if ($sql !== self::sql($plan, $datos)) {
            throw new RuntimeException('El respaldo SQL no coincide con el corte académico. Extrae nuevamente el ZIP.');
        }
        [, $filas] = explode("\nSTART TRANSACTION;\n", $sql, 2);
        return [hash('sha256', $sql), explode("\n", substr($filas, 0, -strlen("\nCOMMIT;\n")))];
    }

    public static function restaurar(PDO $db, array $plan, array $datos, array $sentencias, array $admin): void
    {
        $cuenta = $datos['administrador'];
        if ($admin['correo'] !== $cuenta['correo']) {
            throw new RuntimeException('La cuenta de evaluación no coincide con el respaldo.');
        }
        foreach ($sentencias as $sentencia) {
            $db->exec($sentencia);
        }
        $cuentaActiva = $db->prepare("UPDATE usuarios u JOIN roles r ON r.id=u.rol_id
            SET u.password_hash=?, u.actualizado_en=u.actualizado_en
            WHERE u.id=? AND u.correo=? AND u.activo=1 AND r.nombre='ADMINISTRADOR'");
        $cuentaActiva->execute([$admin['hash'], $cuenta['id'], $cuenta['correo']]);
        if ($cuentaActiva->rowCount() !== 1 || self::capturar($db, $plan) !== $datos['tablas']) {
            throw new RuntimeException('La restauración no coincide con los datos académicos.');
        }
    }
}
