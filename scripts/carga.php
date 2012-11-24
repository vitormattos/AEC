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
        'stop'          => 'Encerra o processo',
        'start'         => 'inicia o processo',
        'status'        => 'Status do processo',
        'intervalo|i=i' => 'Intervalo em segundos entre cada execução',
    ));
    $options = $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
    echo $e->getUsageMessage();
    exit;
}

if(!$opts->getOptions()) {
    echo $opts->getUsageMessage();
    exit;
} if($opts->stop) {
    exec("ps -ef | grep 'carga.php' | grep -v grep | awk '{print $2}' | xargs kill -9");
} elseif($opts->status) {
    $rodando = exec("ps -ef | grep 'carga.php' | grep -v grep | grep -v status");
    if($rodando) {
        $rodando = explode("\n", $rodando);
        echo 'Rodando - '.count($rodando).' processo'.(count($rodando)>1?'s':'');
    } else {
        echo "Morto";
    }
    echo "\n";
} elseif($opts->start) {
    $robot = new Robot_Aec();
    do {
        try{
            $robot->runBackground('Robot_Aec', 'searchOnline');
        }  catch (Exception $e) {}
        sleep($opts->intervalo?:10);
    } while(1);
}