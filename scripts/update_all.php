<?php
// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);
$application->bootstrap();

$robot = new Robot_Aec();

$db = new Zend_Db_Adapter_Pdo_Mysql(array(
    'dbname' => 'aec',
    'username' => 'root',
    'password' => 'root'
));
$result = $db->fetchAll('SELECT id FROM usuario WHERE tem_filhos IS NULL');
foreach($result as $field) {
    $user = $robot->getUser($field['id']);
    if($user) {
        echo $field['id']."\n";
        $robot->save($user, $field['id']);
    }
}