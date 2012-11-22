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
        'class|c=w'  => 'Nome da classe',
        'method|w=w' => 'Método a ser executado',
        'args|a=s'   => 'array serializado e em base64 dos argumentos da classe'
    ));
    $options = $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
    echo $e->getUsageMessage();
    exit;
}

if(!class_exists($opts->class)) {
    echo "Classe inválida\n";
    echo $e->getUsageMessage();
    exit;
}

$object = new $opts->class();
if(!is_object($object)) {
    echo "Falha ao instanciar objeto\n";
    echo $e->getUsageMessage();
    exit;
}

if(!method_exists($object, $opts->method)) {
    echo "Método inexistente";
    echo $e->getUsageMessage();
    exit;
}

$args = base64_decode($opts->args);
$args = unserialize($args);
if(!is_array($args)) {
    echo "Array de argumentos inválido\n";
    echo $e->getUsageMessage();
    exit;
}

$return = call_user_func_array(array($object, $opts->method), $args);
Zend_Debug::dump($return);