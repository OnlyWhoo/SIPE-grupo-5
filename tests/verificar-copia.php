<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';
use Sipe\Installation\{DatosAcademicos,Entrega,Installer,Schema};

$nombre = $argv[1] ?? '';
if (!preg_match('/\Asipe_grupo_5(?:_[a-zA-Z0-9]+)*\z/D', $nombre)) {
    throw new RuntimeException('Indica una base de ensayo o copia restaurada de SIPE Grupo 5.');
}
$db = Installer::connect([
    'host'=>'127.0.0.1','port'=>'3306','user'=>getenv('SIPE_TEST_DB_USER') ?: 'root',
    'password'=>getenv('SIPE_TEST_DB_PASSWORD') ?: '',
]);
$db->exec('USE '.DatosAcademicos::identificar($nombre));
$plan = Entrega::read(ROOT.'/database/entrega-plan.json');
$datos = DatosAcademicos::leer(ROOT.'/database');
if (str_starts_with($nombre,'sipe_grupo_5_sql_')) {
    foreach($plan['tables'] as $tabla=>$definicion) {
        Schema::table($db, $nombre, $tabla, $definicion['signature']);
    }
    Schema::view($db, $nombre, $plan['view']['name'], $plan['view']['definition']);
} else {
    Schema::verify($db, $nombre, $plan);
}
$restauradas = DatosAcademicos::capturar($db, $plan);
if (DatosAcademicos::secuencias($db, $nombre) !== $datos['secuencias']) {
    throw new RuntimeException('Diferencia en las secuencias de identificadores.');
}
$total = 0;
foreach ($datos['tablas'] as $nombre=>$origen) {
    if ($origen !== $restauradas[$nombre]) {
        throw new RuntimeException('Diferencia de datos en '.$nombre.'.');
    }
    $filas = count($origen['filas']);
    $total += $filas;
    echo "OK $nombre: $filas filas idénticas\n";
}
echo "OK Total: $total filas, con fotos binarias, QR, IDs y relaciones comparados.\n";
