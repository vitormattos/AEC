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

$shm = shm_attach(12345, 524288);
$pilha = @shm_get_var($shm, 1);
do {
    $pilha = @shm_get_var($shm, 1);
    if(is_array($pilha))
    foreach($pilha as $id => $force_update) {
        $ignore = shm_get_var($shm, 2)?:array();
        if(array_key_exists($id, $ignore)) continue;
        $dir = '';
        $strlen = strlen($id);
        for($k = $strlen-4; $k >= 0 ; $k--) {
            $dir = substr($id, $k-$strlen, 1).'/'.$dir;
        }
        $img_dir = realpath(dirname(__FILE__).'/../public/img/fotos/');
        if(!is_dir($img_dir.'/'.$dir)) mkdir($img_dir.'/'.$dir, 0777, true);
        if(file_exists($img_dir.'/'.$dir.$id.'t.jpg') &&
           file_exists($img_dir.'/'.$dir.$id.'p1.jpg') &&
           !$force_update) continue;

        $url = '';
        $strlen = strlen($id);
        for($k = $strlen-5; $k >= 0 ; $k--) {
            $url = substr($id, $k-$strlen, 1).'/'.$url;
        }
        $url = 'http://images.amoremcristo.com/images/usuarios_thumbs'.
            str_repeat('/0', (12-strlen($url))/2).'/'.$url.
            'usr'.$id.'t1.jpg';

        $img = @file_get_contents($url);
        if($img) {
            echo $img_dir.'/'.$dir.$id."t.jpg\n";
            file_put_contents($img_dir.'/'.$dir.$id.'t.jpg', $img);
            $robot->save(array('url_thumb' => $url), $id);
            for($k=1;$k<=5;$k++) {
                $name = str_replace('_thumbs', '', $url);
                $name = str_replace('t1.jpg', "p$k.jpg", $name);
                $img = @file_get_contents($name);
                if($img) {
                    echo $img_dir.'/'.$dir.$id."p$k.jpg\n";
                    file_put_contents($img_dir.'/'.$dir.$id."p$k.jpg", $img);
                } else break;
            }
        }
        $ignore[$id] = true;
        shm_put_var($shm, 2, $ignore);
    }
    usleep(50000);
} while(1);