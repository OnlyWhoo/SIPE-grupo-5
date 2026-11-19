<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__).'/app/bootstrap.php';

use Sipe\Installation\{DatosAcademicos, Entrega, Installer, Schema};

try {
    $configPath = $argv[1] ?? '';
    $mode = $argv[2] ?? '';
    if (!is_file($configPath) || !in_array($mode, ['--exportar', '--verificar'], true)) {
        throw new RuntimeException('Indica la ruta al local.php activo de SIPE V2 y --exportar o --verificar.');
    }
    $config = require $configPath;
    if (!preg_match('/\Amysql:host=(?:127\.0\.0\.1|localhost);port=3306;dbname=(sipe_v2(?:_[a-z0-9]+)*);charset=utf8mb4\z/D', $config['dsn'], $match)) {
        throw new RuntimeException('La configuración no corresponde a la base local de SIPE V2 en XAMPP.');
    }
    $database = $match[1];
    $folder = ROOT.'/database';
    $planPath = $folder.'/entrega-plan.json';
    $plan = Entrega::read($planPath);
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    $db->exec("SET time_zone='-05:00'");
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    try {
        $marker = $db->query('SELECT owner,plan,phase FROM sipe_v2_installation WHERE id=1')->fetch();
        if (!$marker || $marker['phase'] !== 'ready' || !hash_equals($config['owner'], $marker['owner'])
            || !hash_equals($config['plan'], $marker['plan']) || !hash_equals(hash_file('sha256', $planPath), $marker['plan'])) {
            throw new RuntimeException('El origen activo o las migraciones de SIPE V2 requieren revisión.');
        }
        Schema::verify($db, $database, $plan);
        foreach (['ROUTINE', 'EVENT', 'TRIGGER'] as $kind) {
            $query = $db->prepare("SELECT 1 FROM information_schema.{$kind}S WHERE {$kind}_SCHEMA=? LIMIT 1");
            $query->execute([$database]);
            if ($query->fetchColumn() !== false) {
                throw new RuntimeException('El origen contiene objetos fuera del esquema de la entrega.');
            }
        }
        $admin = $db->prepare("SELECT u.id,u.correo FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.correo=? AND u.activo=1 AND r.nombre='ADMINISTRADOR'");
        $admin->execute([Installer::EVALUATION_EMAIL]);
        $account = $admin->fetch();
        if (!$account) {
            throw new RuntimeException('El origen no contiene la cuenta administrativa de evaluación esperada.');
        }
        $data = [
            'version' => 1,
            'administrador' => array_map('strval', $account),
            'secuencias' => DatosAcademicos::secuencias($db, $database),
            'tablas' => DatosAcademicos::capturar($db, $plan),
        ];
        $db->exec('COMMIT');
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
    $options = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($mode === '--exportar') {
        $sql = DatosAcademicos::sql($plan, $data);
        $json = json_encode($data, $options)."\n";
        if (file_put_contents($folder.'/exportacion-academica.sql', $sql) !== strlen($sql)
            || file_put_contents($folder.'/datos-academicos.json', $json) !== strlen($json)) {
            throw new RuntimeException('No se pudieron escribir los archivos del respaldo académico.');
        }
    }
    if (DatosAcademicos::leer($folder) !== $data) {
        throw new RuntimeException('La copia difiere de los datos del origen en el momento de la comparación.');
    }
    [$hash] = DatosAcademicos::respaldo($folder, $plan, $data);
    $counts = array_map(static fn(array $table): int => count($table['filas']), $data['tablas']);
    echo json_encode(['base' => $database, 'tablas' => $counts, 'total' => array_sum($counts), 'sha256_sql' => $hash], $options), "\n";
} catch (Throwable $error) {
    $reason = $error instanceof PDOException ? 'SQLSTATE '.$error->getCode() : $error->getMessage();
    fwrite(STDERR, "Respaldo detenido. $reason\n");
    exit(1);
}
