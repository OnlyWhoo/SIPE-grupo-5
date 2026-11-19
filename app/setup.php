<?php
declare(strict_types=1);

use Sipe\Installation\{DatosAcademicos,Installer};
use Sipe\Middleware\Security;

$installer=new Installer($private,ROOT.'/database/entrega-plan.json');
$correo=DatosAcademicos::leer(ROOT.'/database')['administrador']['correo'];
$parameters=$installer->saved()['parameters']??[
    'host'=>'127.0.0.1','port'=>'3306','database'=>'sipe_grupo_5','user'=>'root','password'=>'',
];
$error='';
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        Security::verify(is_string($_POST['csrf']??null)?$_POST['csrf']:null);
        $parameters=Installer::parameters($_POST);
        $password=$_POST['admin_password']??'';
        if(!is_string($password)){throw new DomainException('Introduce una contraseña válida.');}
        Security::password($password);
        $installer->install($parameters,$base,[
            'correo'=>$correo,'hash'=>password_hash($password,PASSWORD_DEFAULT),'password'=>$password,
        ]);
        session_regenerate_id(true);
        $_SESSION=[];
        redirect('login');
    }elseif($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit('Método no permitido.');}
}catch(DomainException $ex){http_response_code(403);$error=$ex->getMessage();}
catch(PDOException $ex){$number=$ex->errorInfo[1]??0;error_log('SIPE Grupo 5 instalación: SQLSTATE '.$ex->getCode().' ('.$number.')');$error=in_array($number,[1044,1045,1142,1227],true)?'La cuenta MySQL requiere credenciales válidas y permiso para crear una base nueva, tablas y vistas. Indica aquí la cuenta autorizada de tu servidor.':'No se pudo completar la preparación. Comprueba MySQL/MariaDB encendido, puerto, credenciales y permisos. La instalación propia se puede reanudar sin borrar bases.';}
catch(RuntimeException $ex){$error=$ex->getMessage();}
$title='Instalación de SIPE Grupo 5';$user=null;ob_start(); ?>
<section class="card"><h1>Preparar SIPE Grupo 5</h1><p>El asistente crea una base independiente y restaura los datos de evaluación mediante MariaDB/MySQL de XAMPP.</p>
<?php if($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif ?>
<p>Indica la conexión de XAMPP y elige un nombre nuevo. Si <strong>sipe_grupo_5</strong> ya existe, usa <strong>sipe_grupo_5_evaluacion</strong> u otro sufijo.</p>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= e(Security::token()) ?>">
<?php foreach(['host'=>'Servidor local','port'=>'Puerto MySQL/MariaDB','database'=>'Base nueva','user'=>'Usuario MySQL','password'=>'Contraseña MySQL'] as $key=>$label): ?>
<label><?= e($label) ?><input name="<?= e($key) ?>" type="<?= $key==='password'?'password':'text' ?>" value="<?= e($key==='password'?'':($parameters[$key]??'')) ?>" <?= $key==='password'?'':'required' ?>></label>
<?php endforeach ?>
<p>Cuenta de evaluación: <strong><?= e($correo) ?></strong></p>
<label>Contraseña nueva para la cuenta de evaluación<input name="admin_password" type="password" minlength="15" maxlength="72" autocomplete="new-password" required></label>
<p class="help">Usa al menos 15 caracteres y conserva la contraseña para el inicio de sesión.</p>
<button>Preparar y continuar</button></form>
<p>Para reanudar una instalación interrumpida, usa el mismo nombre de base, la conexión autorizada y la misma contraseña de evaluación.</p></section>
<?php $content=ob_get_clean();require ROOT.'/app/Views/layout.php';
