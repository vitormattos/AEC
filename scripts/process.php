<?php

$shm = shm_attach(12356, 524288);
do {
    $pilha = @shm_get_var($shm, 1);
    foreach($pilha as $id => $thumb_url) {
        $ignore = shm_get_var($shm, 2)?:array();
        if(array_key_exists($id, $ignore)) continue;
        $dir = '';
        $strlen = strlen($id);
        for($k = $strlen-4; $k >= 0 ; $k--) {
            $dir = substr($id, $k-$strlen, 1).'/'.$dir;
        }
        $img_dir = realpath(dirname(__FILE__).'/../public/img/');
        if(!is_dir($img_dir.'/'.$dir)) mkdir($img_dir.'/'.$dir, 0777, true);
        if(file_exists($img_dir.'/'.$dir.$id.'t.jpg')) continue;
        $img = @file_get_contents($thumb_url);
        if($img) {
            echo $img_dir.'/'.$dir.$id."t.jpg\n";
            file_put_contents($img_dir.'/'.$dir.$id.'t.jpg', $img);
            for($k=1;$k<=5;$k++) {
                $name = str_replace('_thumbs', '', $thumb_url);
                $name = str_replace('t1.jpg', "p$k.jpg", $name);
                $img = @file_get_contents($name);
                if($img) {
                    echo $img_dir.'/'.$dir.$id."p$k.jpg\n";
                    file_put_contents($img_dir.'/'.$dir.$id."p$k.jpg", $img);
                } else break;
            }
        } else {
            $ignore[$id] = true;
            shm_put_var($shm, 2, $ignore);
        }
    }
    usleep(50000);
} while(1);