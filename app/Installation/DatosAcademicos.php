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
            || !is_array($datos['administrador'] ?? null)
            || !is_string($datos['administrador']['correo'] ?? null)
            || !is_string($datos['administrador']['id'] ?? null)) {
            throw new RuntimeException('El archivo de datos académicos tiene un formato incompatible.');
        }
        return $datos;
    }

    public static function restaurar(PDO $db, array $plan, array $datos, array $admin): void
    {
        $tablas = $datos['tablas'];
        $cuenta = $datos['administrador'];
        if (array_keys($tablas) !== array_keys($plan['tables']) || $admin['correo'] !== $cuenta['correo']) {
            throw new RuntimeException('Los datos académicos o su cuenta inicial no corresponden a la edición.');
        }
        foreach ($plan['tables'] as $nombre => $tabla) {
            $columnas = self::columnas($tabla);
            if (($tablas[$nombre]['columnas'] ?? null) !== $columnas || !is_array($tablas[$nombre]['filas'] ?? null)) {
                throw new RuntimeException('El conjunto académico no coincide con la tabla '.$nombre.'.');
            }
            $campos = implode(',', array_map(self::identificar(...), $columnas));
            $argumentos = implode(',', array_fill(0, count($columnas), '?'));
            $insertar = $db->prepare('INSERT INTO '.self::identificar($nombre)." ($campos) VALUES ($argumentos)");
            $indices = array_flip($columnas);
            foreach ($tablas[$nombre]['filas'] as $fila) {
                if (!is_array($fila) || !array_is_list($fila) || count($fila) !== count($columnas)) {
                    throw new RuntimeException('Una fila académica tiene columnas inesperadas.');
                }
                if ($nombre === 'usuarios') {
                    if ($fila[$indices['password_hash']] !== self::MARCA_SIN_CLAVE) {
                        throw new RuntimeException('La exportación no admite contraseñas de origen.');
                    }
                    if ($fila[$indices['id']] === $cuenta['id'] && $fila[$indices['correo']] === $cuenta['correo']) {
                        $fila[$indices['password_hash']] = $admin['hash'];
                    }
                } elseif ($nombre === 'fotografias_prestatarios') {
                    $foto = base64_decode($fila[$indices['contenido']], true);
                    if ($foto === false || (@getimagesizefromstring($foto)['mime'] ?? null) !== $fila[$indices['mime']]) {
                        throw new RuntimeException('Una fotografía académica no es válida.');
                    }
                    $fila[$indices['contenido']] = $foto;
                }
                foreach ($fila as $indice => $valor) {
                    $tipo = $valor === null ? PDO::PARAM_NULL : PDO::PARAM_STR;
                    if ($nombre === 'fotografias_prestatarios' && $indice === $indices['contenido']) {
                        $tipo = PDO::PARAM_LOB;
                    }
                    $insertar->bindValue($indice + 1, $valor, $tipo);
                }
                $insertar->execute();
            }
        }
        $verificar = $db->prepare("SELECT u.password_hash FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.id=? AND u.correo=? AND u.activo=1 AND r.nombre='ADMINISTRADOR'");
        $verificar->execute([$cuenta['id'], $cuenta['correo']]);
        if ($verificar->fetchColumn() !== $admin['hash'] || self::capturar($db, $plan) !== $tablas) {
            throw new RuntimeException('La restauración no coincide con los datos académicos.');
        }
    }
}
