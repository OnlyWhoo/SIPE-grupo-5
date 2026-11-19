<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/app/bootstrap.php';

use Sipe\Installation\{DatosAcademicos,Entrega,Installer,Schema};

$origen = $argv[1] ?? '';
$pendientes = $argv[2] ?? '';
if (!preg_match('/\Asipe_grupo_5_fuente_[0-9]+\z/D', $origen) || !is_file($pendientes)) {
    fwrite(STDERR, "Indica una copia restaurada sipe_grupo_5_fuente_* y el inventario de procedencia.\n");
    exit(1);
}

$db = Installer::connect([
    'host'=>'127.0.0.1', 'port'=>'3306',
    'user'=>getenv('SIPE_TEST_DB_USER') ?: 'root',
    'password'=>getenv('SIPE_TEST_DB_PASSWORD') ?: '',
]);
$db->exec("USE $origen");
$plan = Entrega::read(ROOT.'/database/entrega-plan.json');
Schema::verify($db, $origen, $plan);
$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try {
    $administradores = $db->query("SELECT u.id,u.correo FROM usuarios u JOIN roles r ON r.id=u.rol_id
        WHERE u.activo=1 AND r.nombre='ADMINISTRADOR' ORDER BY u.id")->fetchAll();
    if (count($administradores) !== 1) {
        throw new RuntimeException('La entrega debe identificar exactamente un administrador activo de evaluación.');
    }
    $admin = array_map('strval', $administradores[0]);
    $tablas = DatosAcademicos::capturar($db, $plan);
    $db->exec('COMMIT');
} catch (Throwable $error) {
    $db->exec('ROLLBACK');
    throw $error;
}
$datos = ['version'=>1, 'administrador'=>$admin, 'tablas'=>$tablas];
$referencias = Entrega::read($pendientes)['referencias_pendientes'];
$opciones = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
file_put_contents(ROOT.'/database/datos-academicos.json', json_encode($datos, $opciones)."\n");
file_put_contents(ROOT.'/database/referencias-pendientes.json', json_encode($referencias, $opciones | JSON_PRETTY_PRINT)."\n");

$sql = "SET NAMES utf8mb4;\n".file_get_contents(ROOT.'/database/schema.sql')."\nSTART TRANSACTION;\n";
foreach ($tablas as $nombre => ['columnas'=>$columnas, 'filas'=>$filas]) {
    $campos = implode(',', array_map(DatosAcademicos::identificar(...), $columnas));
    foreach ($filas as $fila) {
        $literales = [];
        foreach ($fila as $i => $valor) {
            $literales[] = match (true) {
                $valor === null => 'NULL',
                $nombre === 'fotografias_prestatarios' && $columnas[$i] === 'contenido' =>
                    "X'".bin2hex(base64_decode($valor, true))."'",
                default => "CONVERT(X'".bin2hex($valor)."' USING utf8mb4)",
            };
        }
        $sql .= 'INSERT INTO '.DatosAcademicos::identificar($nombre)." ($campos) VALUES (".implode(',', $literales).");\n";
    }
}
file_put_contents(ROOT.'/database/exportacion-academica.sql', $sql."COMMIT;\n");
echo json_encode(array_map(static fn(array $tabla): int => count($tabla['filas']), $tablas), $opciones), "\n";
