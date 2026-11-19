<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use Sipe\Installation\{Installer,Schema,Local,DatosAcademicos};
$runtime=ROOT.'/var/runtime';$password=getenv('SIPE_TEST_DB_PASSWORD')?:'';
$rootUser=getenv('SIPE_TEST_DB_USER')?:'root';
$pdo=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',$rootUser,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$n=0;$fail=0;
function check(string $label,callable $fn): void {global $n,$fail;$n++;try{$fn();echo "OK $label\n";}catch(Throwable $e){$fail++;echo "FAIL $label: ".get_class($e).': '.$e->getMessage()."\n";}}
function expect(bool $ok): void {if(!$ok){throw new RuntimeException('Unexpected result');}}
function instance(string $suffix): array {
 global $runtime,$password,$rootUser;
 $dir=$runtime.'/state-'.$suffix.'-'.bin2hex(random_bytes(4));mkdir($dir,0700,true);
 $params=['host'=>'127.0.0.1','port'=>'3306','database'=>'sipe_grupo_5_test_'.bin2hex(random_bytes(5)),'user'=>$rootUser,'password'=>$password];
 return [new Installer($dir,ROOT.'/database/entrega-plan.json'),$params,$dir];
}
check('guard remoto, proxy, rebinding y loopback',function(){expect(Local::allowed(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'localhost:8085']));expect(!Local::allowed(['REMOTE_ADDR'=>'192.168.1.1','HTTP_HOST'=>'localhost']));expect(!Local::allowed(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'evil.example']));expect(!Local::allowed(['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'localhost','HTTP_X_FORWARDED_FOR'=>'1.2.3.4']));});
check('parámetros rechazan base original, inyección y host remoto',function(){foreach([['database'=>'sipe'],['database'=>'mysql'],['database'=>'sipe_v2'],['database'=>'sipe_grupo_5;DROP'],['host'=>'example.com']] as $bad){try{Installer::parameters($bad);throw new LogicException('Accepted bad parameter');}catch(RuntimeException $e){if($e instanceof LogicException){throw $e;}}}});
check('base ajena intacta sin marcador',function()use($pdo){[$installer,$p]=instance('unknown');$pdo->exec('CREATE DATABASE `'.$p['database'].'`');$pdo->exec('CREATE TABLE `'.$p['database'].'`.unrelated(id INT PRIMARY KEY,note VARCHAR(50))');$pdo->exec("INSERT INTO `".$p['database']."`.unrelated VALUES(1,'synthetic sentinel')");try{$installer->install($p);throw new LogicException('Accepted unknown DB');}catch(RuntimeException $e){if($e instanceof LogicException){throw $e;}expect(str_contains($e->getMessage(),'ya existe'));}expect($pdo->query('SELECT note FROM `'.$p['database'].'`.unrelated')->fetchColumn()==='synthetic sentinel');});
check('base preexistente vacía se inicializa',function()use($pdo){[$installer,$p]=instance('empty');$pdo->exec('CREATE DATABASE `'.$p['database'].'`');$installer->install($p);expect($pdo->query('SELECT phase FROM `'.$p['database'].'`.sipe_v2_installation')->fetchColumn()==='ready');});
foreach(['database','marker','owned','created:estudiantes','schema','seed','configured'] as $point){
check('reanudar interrupción '.$point,function()use($point,$pdo){[$installer,$p,$dir]=instance('resume');putenv('SIPE_V2_TEST_FAIL_AT='.$point);try{$installer->install($p);throw new LogicException('No interruption');}catch(RuntimeException $e){if($e instanceof LogicException){throw $e;}expect(str_contains($e->getMessage(),'simulada'));}finally{putenv('SIPE_V2_TEST_FAIL_AT');}
 expect(!is_file($dir.'/local.php'));
 if($point==='seed'){
    $pdo->exec('USE `'.$p['database'].'`');
    expect($pdo->query('SELECT phase FROM sipe_v2_installation')->fetchColumn()==='schema');
    expect((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===0);
    expect((int)$pdo->query('SELECT COUNT(*) FROM fotografias_prestatarios')->fetchColumn()===0);
 }
 $settings=$installer->install($p);expect(is_file($dir.'/local.php'));$pdo->exec('USE `'.$p['database'].'`');expect((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()===1);
 $hash=$pdo->query('SELECT password_hash FROM usuarios')->fetchColumn();expect(password_verify('Sipe2026!',$hash));
 expect(DatosAcademicos::capturar($pdo, json_decode(file_get_contents(ROOT.'/database/entrega-plan.json'),true,512,JSON_THROW_ON_ERROR))===DatosAcademicos::leer(ROOT.'/database')['tablas']);expect($pdo->query('SELECT phase FROM sipe_v2_installation')->fetchColumn()==='ready');
 expect($installer->install($p)===$settings);expect($pdo->query('SELECT password_hash FROM usuarios')->fetchColumn()===$hash);
});}
check('identificador privado incorrecto rechaza apropiación',function()use($pdo){[$installer,$p,$dir]=instance('owner');putenv('SIPE_V2_TEST_FAIL_AT=owned');try{$installer->install($p);}catch(RuntimeException){}finally{putenv('SIPE_V2_TEST_FAIL_AT');}$pdo->exec('USE `'.$p['database'].'`');$pdo->exec("UPDATE sipe_v2_installation SET owner=REPEAT('a',64)");try{$installer->install($p);throw new LogicException('Wrong owner accepted');}catch(RuntimeException $e){if($e instanceof LogicException){throw $e;}expect(str_contains($e->getMessage(),'propietario'));}});
check('tabla propia divergente no se reconstruye',function()use($pdo){[$installer,$p]=instance('diverge');putenv('SIPE_V2_TEST_FAIL_AT=created:estudiantes');try{$installer->install($p);}catch(RuntimeException){}finally{putenv('SIPE_V2_TEST_FAIL_AT');}$pdo->exec('USE `'.$p['database'].'`');$pdo->exec('ALTER TABLE estudiantes ADD unexpected VARCHAR(10)');try{$installer->install($p);throw new LogicException('Divergence accepted');}catch(RuntimeException $e){if($e instanceof LogicException){throw $e;}expect(str_contains($e->getMessage(),'no coincide'));}expect((int)$pdo->query('SELECT COUNT(*) FROM estudiantes')->fetchColumn()===0);});
check('cambiar de nombre conserva propiedad pendiente anterior',function(){[$installer,$a,$dir]=instance('history');$b=$a;$b['database']='sipe_grupo_5_test_'.bin2hex(random_bytes(5));foreach([$a,$b] as $p){putenv('SIPE_V2_TEST_FAIL_AT=owned');try{$installer->install($p);}catch(RuntimeException $e){expect(str_contains($e->getMessage(),'simulada'));}finally{putenv('SIPE_V2_TEST_FAIL_AT');}}$settings=$installer->install($a);expect(str_contains($settings['dsn'],$a['database']));});
check('cuenta sin CREATE recibe explicación y no crea base',function()use($pdo){[$installer,$p]=instance('privileges');$user='v2_restricted_'.bin2hex(random_bytes(3));$secret=bin2hex(random_bytes(20));$pdo->exec('CREATE USER '.$pdo->quote($user)."@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($secret));$p['user']=$user;$p['password']=$secret;try{$installer->install($p);throw new LogicException('Unauthorized creation');}catch(RuntimeException $e){if($e instanceof LogicException){throw $e;}expect(str_contains($e->getMessage(),'no permite crear'));}});
echo "$n comprobaciones del instalador, $fail fallos.\n";exit($fail?1:0);
