<?php
declare(strict_types=1);

use Sipe\Installation\{Installer, Local, Schema};
use Sipe\Models\Database;

define('ROOT',dirname(__DIR__));
date_default_timezone_set('America/Bogota');
spl_autoload_register(function(string $class): void {
    if(str_starts_with($class,'Sipe\\')){$file=ROOT.'/app/'.str_replace('\\','/',substr($class,5)).'.php';if(is_file($file)){require $file;}}
});
$config=require ROOT.'/config/example.php';
function config(): array {global $config;return $config;}
function e(mixed $value): string {return htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function url(string $route=''): string {return rtrim(config()['base_path'],'/').'/index.php?r='.rawurlencode($route);}
function redirect(string $route): never {header('Location: '.url($route),true,303);exit;}
function view(string $name,array $data=[]): void {extract($data,EXTR_SKIP);ob_start();require ROOT.'/app/Views/'.$name.'.php';$content=ob_get_clean();require ROOT.'/app/Views/layout.php';}
if(PHP_SAPI==='cli'){$file=getenv('SIPE_CONFIG');if($file&&is_file($file)){$config=require $file;}return;}
ini_set('display_errors','0');ini_set('log_errors','1');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('Cache-Control: no-store');
Local::guard();
try {
    if(PHP_VERSION_ID<80200){throw new RuntimeException('Se requiere PHP 8.2 o superior.');}
    foreach(['pdo_mysql','mbstring','xmlwriter','dom','session','hash'] as $extension){if(!extension_loaded($extension)){throw new RuntimeException('Habilita la extensión PHP '.$extension.' en tu XAMPP.');}}
    $base=$config['base_path'];
    Local::protection($base);
    $private=Local::privateDirectory();
    ini_set('error_log',$private.'/php-error.log');
    $configFile=$private.'/local.php';
    $migrationLock=fopen($private.'/migration.lock','c');
    if(!$migrationLock||!flock($migrationLock,LOCK_SH|LOCK_NB)){throw new RuntimeException('La actualización de SIPE Grupo 5 está en curso. Vuelve a abrir la aplicación al finalizar.');}

    if (!is_file($configFile)) {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            exit('Abre la página de inicio de SIPE Grupo 5 para preparar la instalación.');
        }
        if (!in_array($_GET['r'] ?? 'dashboard', ['dashboard', 'login'], true)) {
            redirect('login');
        }
        require ROOT.'/app/setup.php';
        redirect('login');
    }

    $config = array_replace($config, require $configFile, [
        'base_path' => $base,
        'secure_cookie' => $config['secure_cookie'],
    ]);
    $db=Database::get();
    try {
        $marker=$db->query('SELECT owner,plan,phase FROM sipe_v2_installation WHERE id=1')->fetch();
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) !== 1146) { throw $error; }
        $marker = false;
    }
    $legacy=($config['demo_local']??false)===true;
    $planFile=ROOT.'/database/'.($legacy?'install-plan.json':'entrega-plan.json');
    $digest=hash_file('sha256',$planFile);
    if(!$marker||$marker['phase']!=='ready'||!hash_equals($config['owner']??'',$marker['owner'])||!hash_equals($config['plan']??'',$marker['plan'])||!hash_equals($digest,$marker['plan'])){throw new RuntimeException('El marcador de instalación no coincide. Conserva los datos y solicita revisión técnica.');}

    $sessions=$private.'/sessions';if(!is_dir($sessions)&&!@mkdir($sessions,0700,true)&&!is_dir($sessions)){throw new RuntimeException('No se pudo crear el almacenamiento privado de sesiones.');}
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    $sessionScope=ROOT.($legacy?'':$digest);
    session_name('SIPEGRUPO5_'.substr(hash('sha256',$sessionScope),0,12));session_save_path($sessions);
    session_set_cookie_params(['httponly'=>true,'secure'=>$config['secure_cookie'],'samesite'=>'Lax','path'=>$base.'/']);session_start();
    if(($_SESSION['schema_verified']??'')!==$digest){$plan=json_decode((string)file_get_contents($planFile),true,512,JSON_THROW_ON_ERROR);Schema::verify($db,(string)$db->query('SELECT DATABASE()')->fetchColumn(),$plan);$_SESSION['schema_verified']=$digest;}
    $config['profiles']=!$legacy;
}catch(Throwable $error){
    http_response_code(503);
    $message = $error instanceof RuntimeException ? $error->getMessage() : 'La preparación de SIPE Grupo 5 requiere revisión técnica.';
    $details = '';
    if ($error instanceof PDOException) {
        $number = (int)($error->errorInfo[1] ?? 0);
        $message = match (true) {
            in_array($number,[1045,1698,2002,2003,2005,2006,2013],true) => 'No se pudo establecer conexión con MariaDB de XAMPP.',
            $number === 1049 => 'La base configurada de SIPE Grupo 5 no está disponible. Verifica la base guardada.',
            in_array($number,[1044,1142,1227],true) => 'La cuenta MariaDB no tiene los permisos necesarios para SIPE Grupo 5.',
            default => 'No se pudo completar la restauración o verificar la base de SIPE Grupo 5.',
        };
        $details = ' SQLSTATE '.$error->getCode().' ('.$number.')';
    }
    if(isset($private)){error_log('SIPE Grupo 5 arranque: '.get_class($error).$details);}
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Revisar instalación de SIPE Grupo 5</title>'
        .'<h1>Revisar instalación de SIPE Grupo 5</h1><p>'.e($message).'</p>'
        .'<p>Comprueba que MySQL/MariaDB esté iniciado en XAMPP, que el puerto sea el configurado '
        .'(3306 por defecto), que las credenciales sean válidas y que la cuenta pueda crear la base y sus tablas y vistas.</p>'
        .'<p>Consulta README.md para la configuración local opcional. Conserva la base y la carpeta privada.</p></html>';
    exit;
}
