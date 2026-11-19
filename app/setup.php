<?php
declare(strict_types=1);

use Sipe\Installation\Installer;

$override = ROOT.'/config/config.local.php';
$connection = is_file($override) ? require $override : [];
if (!is_array($connection)) {
    throw new RuntimeException('config/config.local.php debe devolver un arreglo de conexión.');
}
(new Installer($private, ROOT.'/database/entrega-plan.json'))->install($connection);
