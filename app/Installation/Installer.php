<?php
declare(strict_types=1);

namespace Sipe\Installation;

use PDO;
use PDOException;
use RuntimeException;

final class Installer
{
    public const EVALUATION_EMAIL = 'profesor@sipe.local';
    private const EVALUATION_PASSWORD = 'Sipe2026!';
    private const MARKER = 'sipe_v2_installation';
    private const STEPS = 'sipe_v2_steps';

    public function __construct(private string $private, private string $planPath) {}

    public static function parameters(array $input): array
    {
        $p = $input + [
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'sipe_grupo_5',
            'user' => 'root',
            'password' => '',
        ];
        if (is_int($p['port'])) {
            $p['port'] = (string)$p['port'];
        }
        foreach (['host', 'port', 'database', 'user', 'password'] as $key) {
            if (!is_string($p[$key])) {
                throw new RuntimeException('Formato inválido en la configuración local de MariaDB.');
            }
        }
        if (!in_array($p['host'], ['127.0.0.1', 'localhost', '::1'], true)
            || !ctype_digit($p['port']) || (int)$p['port'] < 1 || (int)$p['port'] > 65535) {
            throw new RuntimeException('Configura el servidor local de XAMPP y su puerto MariaDB (3306 de forma predeterminada).');
        }
        if (strlen($p['database']) > 64 || !preg_match('/\Asipe_grupo_5(?:_[a-z0-9]+)*\z/D', $p['database'])) {
            throw new RuntimeException('Elige sipe_grupo_5 o un nombre en minúsculas que comience por sipe_grupo_5_.');
        }
        if ($p['user'] === '' || strlen($p['user']) > 80 || strlen($p['password']) > 1024) {
            throw new RuntimeException('Revisa la cuenta MariaDB de XAMPP.');
        }
        $p['port'] = (string)(int)$p['port'];
        return $p;
    }

    private static function dsn(array $p, ?string $database = null): string
    {
        return 'mysql:host='.$p['host'].';port='.$p['port'].';charset=utf8mb4'.($database === null ? '' : ';dbname='.$database);
    }

    public static function connect(array $p): PDO
    {
        $pdo = new PDO(self::dsn($p), $p['user'], $p['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
        $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $minimum = stripos($version, 'MariaDB') === false ? '8.0.16' : '10.4';
        if (version_compare($version, $minimum, '<')) {
            throw new RuntimeException('Se requiere MariaDB 10.4+ o MySQL 8.0.16+ con restricciones CHECK efectivas.');
        }
        $pdo->exec("SET time_zone='-05:00'");
        $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec('SET SESSION foreign_key_checks=1, unique_checks=1');
        return $pdo;
    }

    private function checkpoint(string $name): void
    {
        if (PHP_SAPI === 'cli' && getenv('SIPE_V2_TEST_FAIL_AT') === $name) {
            throw new RuntimeException('Interrupción simulada: '.$name);
        }
    }

    private function objects(PDO $db, string $database): array
    {
        foreach (['ROUTINES', 'EVENTS', 'TRIGGERS'] as $kind) {
            $column = rtrim($kind, 'S').'_SCHEMA';
            $query = $db->prepare("SELECT 1 FROM information_schema.$kind WHERE $column=? LIMIT 1");
            $query->execute([$database]);
            if ($query->fetchColumn() !== false) {
                throw new RuntimeException('La base de destino contiene rutinas, eventos o disparadores. Usa una base independiente.');
            }
        }
        $query = $db->prepare('SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=?');
        $query->execute([$database]);
        return $query->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function claim(PDO $db, array $objects, string $path, string $plan, string $backup, string $version): array
    {
        $state = is_file($path) ? Entrega::read($path) : null;
        if ($state === null && $objects !== []) {
            throw new RuntimeException('La base de destino ya existe y contiene datos u objetos de otra instalación. Usa una base independiente en config/config.local.php.');
        }
        if ($state === null) {
            $state = ['owner' => bin2hex(random_bytes(32)), 'plan' => $plan, 'backup' => $backup];
            Local::write($path, json_encode($state, JSON_THROW_ON_ERROR));
        }
        if (!hash_equals($state['plan'] ?? '', $plan) || !hash_equals($state['backup'] ?? '', $backup)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $state['owner'] ?? '')) {
            throw new RuntimeException('El respaldo o la propiedad de la instalación interrumpida cambiaron. Conserva la base y solicita revisión técnica.');
        }
        $name = self::MARKER;
        $comment = 'sipe-grupo-5:'.$state['owner'];
        if ($objects === []) {
            $db->exec("CREATE TABLE $name (
                id TINYINT PRIMARY KEY, owner CHAR(64) NOT NULL, plan CHAR(64) NOT NULL,
                phase VARCHAR(20) NOT NULL, version VARCHAR(20) NOT NULL, CHECK(id=1)
            ) ENGINE=InnoDB COMMENT=".$db->quote($comment));
            $this->checkpoint('marker');
            $objects[$name] = $comment;
        }
        if (($objects[$name] ?? null) !== $comment) {
            throw new RuntimeException('La base de destino no tiene un marcador de propiedad de esta instalación. Usa una base independiente.');
        }
        $marker = $db->query("SELECT owner,plan,phase FROM $name WHERE id=1")->fetch();
        if ($marker === false && $objects === [$name => $comment]
            && (int)$db->query("SELECT COUNT(*) FROM $name")->fetchColumn() === 0) {
            $db->prepare("INSERT INTO $name VALUES(1,?,?,'schema',?)")->execute([$state['owner'], $plan, $version]);
            $marker = ['owner' => $state['owner'], 'plan' => $plan, 'phase' => 'schema'];
        }
        if (!$marker || !hash_equals($state['owner'], $marker['owner']) || !hash_equals($plan, $marker['plan'])) {
            throw new RuntimeException('El propietario o el esquema de la base de destino no coincide.');
        }
        return $marker;
    }

    private function restore(PDO $db, string $database, array $plan, array $data, array $statements, string $password): void
    {
        $objects = $this->objects($db, $database);
        $allowed = [...array_keys($plan['tables']), $plan['view']['name'], self::MARKER, self::STEPS];
        if (array_diff(array_keys($objects), $allowed)) {
            throw new RuntimeException('La base pendiente contiene objetos ajenos al respaldo.');
        }
        foreach ($plan['tables'] as $name => $table) {
            if (array_key_exists($name, $objects)) {
                Schema::table($db, $database, $name, $table['signature']);
                if ((int)$db->query('SELECT COUNT(*) FROM '.DatosAcademicos::identificar($name))->fetchColumn() !== 0) {
                    throw new RuntimeException('La instalación pendiente contiene datos inesperados. Conserva la base y solicita revisión técnica.');
                }
            }
        }
        $steps = self::STEPS;
        if (!array_key_exists($steps, $objects)) {
            $db->exec("CREATE TABLE $steps(id VARCHAR(100) PRIMARY KEY,checksum CHAR(64) NOT NULL) ENGINE=InnoDB");
        }
        $step = $db->prepare("SELECT checksum FROM $steps WHERE id=?");
        $record = $db->prepare("INSERT INTO $steps VALUES(?,?)");
        foreach ($plan['tables'] as $name => $table) {
            if (!array_key_exists($name, $objects)) {
                $db->exec(DatosAcademicos::ddl($name, $table, $data));
                $this->checkpoint('created:'.$name);
            }
            $checksum = hash('sha256', $table['sql']);
            $step->execute([$name]);
            $done = $step->fetchColumn();
            if ($done === false) {
                $record->execute([$name, $checksum]);
            } elseif (!hash_equals($checksum, $done)) {
                throw new RuntimeException('Paso de esquema divergente: '.$name);
            }
        }
        $view = $plan['view'];
        if (!array_key_exists($view['name'], $objects)) {
            $db->exec($view['sql']);
        }
        Schema::verify($db, $database, $plan);
        $this->checkpoint('schema');

        $db->beginTransaction();
        try {
            DatosAcademicos::restaurar($db, $plan, $data, $statements, [
                'correo' => self::EVALUATION_EMAIL,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            if (DatosAcademicos::secuencias($db, $database) !== $data['secuencias']) {
                throw new RuntimeException('Las secuencias de identificadores no coinciden con el respaldo.');
            }
            $this->checkpoint('seed');
            $db->exec("UPDATE ".self::MARKER." SET phase='ready' WHERE id=1");
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public function install(array $input, string $password = self::EVALUATION_PASSWORD): array
    {
        $file = @fopen($this->private.'/installation.lock', 'c');
        if (!$file || !flock($file, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear la instalación local.');
        }
        $db = null;
        $locked = false;
        try {
            clearstatcache();
            $configFile = $this->private.'/local.php';
            if (is_file($configFile)) {
                return require $configFile;
            }
            $p = self::parameters($input);
            $database = $p['database'];
            $plan = Entrega::read($this->planPath);
            $digest = hash_file('sha256', $this->planPath);
            $directory = dirname($this->planPath);
            $data = DatosAcademicos::leer($directory);
            [$backup, $statements] = DatosAcademicos::respaldo($directory, $plan, $data);
            $db = self::connect($p);
            $lock = 'sipe-grupo-5:'.substr(hash('sha256', $database), 0, 48);
            $query = $db->prepare('SELECT GET_LOCK(?, 10)');
            $query->execute([$lock]);
            $locked = (int)$query->fetchColumn() === 1;
            if (!$locked) {
                throw new RuntimeException('Otra instalación está en curso. Vuelve a abrir SIPE en unos segundos.');
            }
            $query = $db->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
            $query->execute([$database]);
            if ($query->fetchColumn() === false) {
                try {
                    $db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (PDOException $error) {
                    if (in_array($error->errorInfo[1] ?? 0, [1044, 1045, 1142, 1227], true)) {
                        throw new RuntimeException('La cuenta MariaDB no permite crear la base de destino. Revisa los permisos de XAMPP.');
                    }
                    throw $error;
                }
                $this->checkpoint('database');
            }
            $db->exec("USE `$database`");
            $path = $this->private.'/ownership-'.hash('sha256', $p['host'].':'.$p['port'].'/'.$database).'.json';
            $marker = $this->claim($db, $this->objects($db, $database), $path, $digest, $backup, $plan['version']);
            $this->checkpoint('owned');
            if ($marker['phase'] === 'schema') {
                $this->restore($db, $database, $plan, $data, $statements, $password);
            } elseif ($marker['phase'] === 'ready') {
                Schema::verify($db, $database, $plan);
                $check = $db->prepare("SELECT u.password_hash FROM usuarios u JOIN roles r ON r.id=u.rol_id
                    WHERE u.correo=? AND u.activo=1 AND r.nombre='ADMINISTRADOR'");
                $check->execute([self::EVALUATION_EMAIL]);
                if (!password_verify($password, (string)$check->fetchColumn())) {
                    throw new RuntimeException('La cuenta de evaluación de la instalación pendiente requiere revisión técnica.');
                }
            } else {
                throw new RuntimeException('Estado de instalación desconocido. Conserva la base y solicita revisión técnica.');
            }
            $this->checkpoint('configured');
            $settings = [
                'dsn' => self::dsn($p, $database),
                'user' => $p['user'],
                'password' => $p['password'],
                'owner' => $marker['owner'],
                'plan' => $digest,
            ];
            Local::write($configFile, "<?php\nreturn ".var_export($settings, true).";\n");
            return $settings;
        } finally {
            if ($locked) {
                try {
                    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lock]);
                } catch (PDOException $error) {
                    error_log('SIPE Grupo 5 bloqueo de instalación: SQLSTATE '.$error->getCode());
                }
            }
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
