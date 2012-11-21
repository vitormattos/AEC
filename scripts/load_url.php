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

try {
    $opts = new Zend_Console_Getopt(array(
        'id|i=i'    => 'Id do usuário',
    ));
    $options = $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
    echo $e->getUsageMessage();
    exit;
}

if(!is_numeric($opts->id)) {
    echo $options->getUsageMessage();
    exit;
}

$shm = shm_attach(12345, 524288);
$ignore = @shm_get_var($shm, 2);
if(in_array($opts->id, $ignore)) {
    exit;
}

$robot = new Robot_Aec();
$user = $robot->getUser($opts->id);
if($user) {
    echo $opts->id."\n";
    $robot->save($user, $opts->id);
}