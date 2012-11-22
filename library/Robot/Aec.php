<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Robot_Aec
 *
 * @author vitor
 */
class Robot_Aec {
    /**
     * Instância do banco
     * @var Zend_Db_Adapter_Pdo_Mysql
     */
    protected $db = null;
    
    /**
     *
     * @var shm_resource
     */
    protected $shm;

    /**
     * configurações extras do sistema
     * @var Zend_Config
     */
    protected $config = array();
    
    public function __construct() {
        $config = new Zend_Config_Ini(
            APPLICATION_PATH.'/configs/application.ini'
        );
        $this->config = new Zend_Config_Ini(
            APPLICATION_PATH.'/configs/'.
            $config->get('development')->get('senhas')
        );
        $this->db = new Zend_Db_Adapter_Pdo_Mysql(array(
            'host' => $this->config->database->host,
            'dbname' => $this->config->database->dbname,
            'username' => $this->config->database->username,
            'password' => $this->config->database->password
        ));
        
        $this->shm = shm_attach(12345, 524288);
    }

    protected function slug($var)
    {
        $acentos = array(
                        'a' => '/À|Á|Â|Ã|Ä|Å/',
                        'a' => '/à|á|â|ã|ä|å/',
                        'e' => '/È|É|Ê|Ë/',
                        'e' => '/è|é|ê|ë/',
                        'i' => '/Ì|Í|Î|Ï/',
                        'i' => '/ì|í|î|ï/',
                        'o' => '/Ò|Ó|Ô|Õ|Ö/',
                        'o' => '/ò|ó|ô|õ|ö/',
                        'u' => '/Ù|Ú|Û|Ü/',
                        'u' => '/ù|ú|û|ü/',
                        'c' => '/ç/',
                        '_' => '/ |-/',
                        ''  => '/,/'
        );
        $var = preg_replace($acentos, array_keys($acentos), trim($var, ' .'));
        $var = strtolower($var);
        return $var;
    }
    
    public function pushPilha($id, $force_update = false) {
        if(!is_numeric($id)) return;
        $pilha = @shm_get_var($this->shm, 1);
        $ignore = @shm_get_var($this->shm, 2);
        // limpeza da pilha
        if(is_array($pilha)) {
            reset($pilha);
            while(count($pilha)>=100) {
                unset($pilha[key($pilha)]);
                unset($ignore[key($pilha)]);
            }
        } else {
            shm_remove($this->shm);
            shm_detach($this->shm);
            $this->shm = shm_attach(12345, 524288);
        }
        $pilha[$id] = $force_update;
        if($force_update) unset($ignore[$id]);
        shm_put_var($this->shm, 1, $pilha);
        shm_put_var($this->shm, 2, $ignore);
    }

    public function getUser($id)
    {
        // fuma bebe
        //$id = 2098045;
        // varias denominações
        //$id = 380885;
        // eu
        // $id = 232785;
        // gringo
        //$id = 2340755;
        //$id = 2246224;
        
        $client = new Zend_Http_Client();

        $client->setUri('http://www.amoremcristo.com/profile_view.asp?id='.$id);
        $response = $client->request();
        if(!$response->isSuccessful()) return;

        $dir = '';
        $strlen = strlen($id);
        for($k = $strlen-5; $k >= 0 ; $k--) {
            $dir = substr($id, $k-$strlen, 1).'/'.$dir;
        }

        $dir = realpath(APPLICATION_PATH . '/../public/').'/img/fotos/'.$dir;
        if(!file_exists($dir.$id.'t.jpg')) {
            $this->pushPilha($id, true);
        } else {
            $user['url_thumb'] = 'http://images.amoremcristo.com/images/usuarios_thumbs'.
                str_repeat('/0', (12-strlen($dir))/2).'/'.$dir.
                'usr'.$id.'t1.jpg';
            $change_date = date("F d Y H:i:s.", filemtime($dir.$id.'t.jpg'));
            if($change_date < $user['updated']) {
                $this->aec->pushPilha($user['id'], true);
            }
        }

        $body = $response->getBody();
        $body = str_replace('&nbsp;', ' ', $body);
        $dom = new Zend_Dom_Query($response->getBody());
        $results = $dom->query('.subheader, tr.odd td, tr.even td');
        foreach($results as $result) {
            // pula o subheader
            if(strpos($result->C14N(), '<img')) {
                $subheader = $this->slug($result->nodeValue);
                $subheaders = array(
                    'meu_perfil',
                    'minha_religiosidade',
                    'perfil_de_quem_eu_busco',
                    'religiosidade_de_quem_eu_busco'
                );
                if(in_array($subheader, $subheaders)) {
                    $subheader = null;
                }
                continue;
            }
            if(strpos($result->C14N(), '<font')) {
                $posicao_separador = strpos($result->nodeValue, ':');
                $key = $this->slug(substr($result->nodeValue, 0, $posicao_separador));
                $value = substr($result->nodeValue, $posicao_separador);
                $value = trim($value, ": \n\r");
                switch($key) {
                    case 'tenho_maior_interesse_em_pessoas_das_seguintes_formacoes':
                        $value = explode(',', $value);
                        array_walk($value, create_function('&$val', '$val = trim($val);'));
                        $user[$key] = $value;
                        break;
                    case 'gostaria_que_pessoa_que_busco_tivesse_a_seguinte_situacao_em_relacao_a_filhos':
                        $key = 'desejada_filhos';
                        $user[$key] = $value;
                        break;
                    case 'estado_civil_de_quem_eu_busco':
                        $value = explode(',', $value);
                        array_walk($value, create_function('&$val', '$val = trim($val);'));
                        $user[$key] = $value;
                        break;
                    case 'a_frequencia_na_igreja_que_mais_se_encaixa_no_meu_perfil_e':
                        $key = 'frequencia_igreja_desejada';
                        $value = explode(',', $value);
                        array_walk($value, create_function('&$val', '$val = trim($val);'));
                        $user[$key] = $value;
                        break;
                    case 'gostaria_que_a_pessoa_que_busco_fosse_de_uma_das_seguintes_denominacoes':
                        $key = 'denominacao_desejada';
                        $value = explode(',', $value);
                        array_walk($value, create_function('&$val', '$val = trim($val);'));
                        $user[$key] = $value;
                        break;
                    case 'sexo':
                        if(array_key_exists('sexo', $user)) {
                            $user['sexo_procurado'] = $value;
                        } else {
                            $user[$key] = $value;
                        }
                        break;
                    default:
                        $user[$key] = $value;
                }
            } elseif(!strlen($result->nodeValue)) {
                continue;
            } elseif(strpos($result->C14N(), 'colspan')) {
                $value = explode(',', $result->nodeValue);
                $user['tem_filhos'] = $value[0] == 'Possuo filhos' ? 'sim' : 'nao';
                $user['quer_ter_filhos'] = trim($value[1]);
                $value[2] = explode(' e ', $value[2]);
                $user['fuma'] = trim($value[2][0]);
                $user['bebe'] = trim($value[2][1], ' .');
            } else {
                $key = $subheader;
                $value = trim($result->nodeValue);
                $user['textos'][$key] = $value;
            }
        }
        return $user;
    }

    public function save($usuario, $id)
    {
        $insert = array();
        $insert['usuario']['id'] = $id;
        foreach($usuario as $key => $value) {
            if($key == 'textos') {
                foreach($value as $chave => $valor) {
                    $insert['usuario'][$chave]=$valor;
                }
            } elseif($key == 'ultimo_acesso') {
                if(isset($usuario['status']) && $usuario['status'] == 'Online') {
                    $child = $this->db->fetchRow("SELECT id FROM $key where $key = '$value' AND usuario_id = $id");
                    if(!$child) {
                        $this->db->insert($key, array(
                            $key => $value,
                            'usuario_id' => $id
                        ));
                    }
                }
                unset($usuario['ultimo_acesso']);
            } elseif($key == 'denominacao') {
                $child = $this->db->fetchRow("SELECT id FROM $key where $key = '$value'");
                if(!$child) {
                    $this->db->insert($key, array($key => $value));
                    $child_id = $this->db->lastInsertId();
                } else {
                    $child_id = $child['id'];
                }
                $insert['usuario']['denominacao_id'] = $child_id;
            } elseif($key == 'denominacao' || is_array($value)) {
                $this->db->delete('usuario_'.$key, 'usuario_id = '.$id);
                foreach($value as $item) {
                    $child = $this->db->fetchRow("SELECT id FROM $key where $key = '$item'");
                    if(!$child) {
                        $this->db->insert($key, array($key => $item));
                        $child_id = $this->db->lastInsertId();
                    } else {
                        $child_id = $child['id'];
                    }
                    $this->db->insert('usuario_'.$key, array(
                        $key.'_id' => $child_id,
                        'usuario_id' => $id
                    ));
                }
            } else {
                $insert['usuario'][$key]=$value;
            }
        }
        $results = $this->db->fetchRow('SELECT id, status FROM usuario where id = '.$id);
        if($results) {
            unset($insert['usuario']['id']);
            if(
                    isset($insert['usuario']['status']) &&
                    $insert['usuario']['status'] == 'Online' &&
                    $results['status'] != 'Online'
                ) {
                $insert['usuario']['updated'] = date('Y-m-d H:i:s');
            } else {
                unset($insert['usuario']['updated']);
            }
            $this->db->update('usuario', $insert['usuario'], 'id = '.$id);
        } else {
            $insert['usuario']['created'] = date('Y-m-d H:i:s');
            $insert['usuario']['updated'] = date('Y-m-d H:i:s');
            $this->db->insert('usuario', $insert['usuario']);
        }
    }
    
    public function getAndSave($id)
    {
        $user = $this->getUser($id);
        if($user) {
            $this->save($user, $id);
            return $id;
        }
    }
    /**
     * Executa um método de uma classe em background
     * 
     * @param string $class_name
     * @param string $method
     * @param Array $args
     */
    public function runBackground($class_name, $method, $args = array())
    {
        if(!class_exists($class_name)) return false;
            $args = serialize(is_array($args) ? $args : array());
            $args = base64_encode($args);

            // joga para background
            $process = realpath(APPLICATION_PATH . '/../scripts/').
                    '/runbackground.php' .
                    ' --class ' . $class_name .
                    ' --method ' . $method .
                    ' --args ' . $args
                    .' >> '.realpath(APPLICATION_PATH . '/../scripts/').'/log2';
            pclose(popen("php $process &", 'r'));
    }
    
    public function searchOnline()
    {
        $client = new Zend_Http_Client();
        a:
        $client->setUri('http://www.amoremcristo.com/search.asp')
                ->setParameterGet(array(
                    'go'      => 'now',
                    'tb'      => 9,
                    'gender'  => 0,
                    'fromage' => 0,
                    'toage'   => 35,
                    'pais'    => 28,
                    'estado'  => 19,
                    'cidade'  => 6935,
                    //'pics'    => 1,
                    'local'   => 1
                ))
                ->setCookieJar($this->getCookie());
        $response = $client->request();

        if(!$response->isSuccessful()) return;

        $body = str_replace('&nbsp;', ' ', $response->getBody());
        $dom = new Zend_Dom_Query($body);
        // verifica se está autenticado
        // senão, faz login e refaz a requisição
        if($dom->query('.header_login a')->count() < 2 && !$recursive) {
            $this->login();
            $client->resetParameters();
            $recursive = true;
            goto a;
        }

        $results = $dom->query('.search_results .details_table td');
        $user = array();
        $users_online = array();
        foreach($results as $result) {
            $j = 0;
            foreach($result->childNodes as $node) {
                if($node->nodeType != 1) continue;
                if($j == 0) {
                    $url = $node->firstChild->firstChild->getAttribute('href');
                    // id
                    preg_match('{id=([0-9]{1,})}', $url, $id);
                    $id = $id[1];
                    // url do perfil
                    $user[$id]['url_perfil'] = $url;
                    // status
                    $user[$id]['status'] = $node
                            ->getElementsByTagName('div')->item(1)
                            ->getElementsByTagName('font')->item(0)
                            ->textContent;
                    if($user[$id]['status'] == 'Online') {
                        $users_online[] = $id;
                    }
                } elseif ($j == 2) {
                    // ultimo acesso
                    $user[$id]['ultimo_acesso'] = $node->getElementsByTagName('div')->item(1)->textContent;
                    $user[$id]['ultimo_acesso'] = explode(':', $user[$id]['ultimo_acesso']);
                    $user[$id]['ultimo_acesso'] = trim($user[$id]['ultimo_acesso'][1]);
                    $user[$id]['ultimo_acesso'] = DateTime::createFromFormat('d/m/Y', $user[$id]['ultimo_acesso']);
                    $user[$id]['ultimo_acesso'] = $user[$id]['ultimo_acesso']->format('Y-m-d');
                }
                $this->save($user[$id], $id);
                $j++;
            }
            // Apenas pega dados do usuário se ele não existir
            $existe = $this->getById($id);
            if(!$existe['apelido']) {
                // joga para background
                $this->runBackground('Robot_Aec', 'getAndSave', array($id));
            }
        }
        if(count($users_online)) {
            $this->db->update(
                'usuario',
                array('status' => 'Offline'),
                'id NOT IN ('.implode(', ', $users_online).')'
            );
        }
        foreach($user as $id => $u) {
            $dir = '';
            $strlen = strlen($id);
            for($k = $strlen-4; $k >= 0 ; $k--) {
                $dir = substr($id, $k-$strlen, 1).'/'.$dir;
            }
            if(file_exists(realpath(APPLICATION_PATH . '/../public/').'/img/fotos/'.$dir.$id.'p1.jpg')) {
                echo 
                '<img src="/img/fotos/'.$dir.$id.'p1.jpg"    >';
            }
        }
        //Zend_Debug::dump($user);
    }

    public function getCookie()
    {
        $cookie = shm_get_var($this->shm, 3);
        if(!$cookie) {
            $this->login();
            $cookie = shm_get_var($this->shm, 3);
        }
        return unserialize($cookie);
    }
    
    public function renewCookie()
    {
        $cookie = $this->getCookie();
        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/')
                ->setCookieJar($cookie);
        $response = $client->request();
        
        $dom = new Zend_Dom_Query($response->getBody());
        if($dom->query('.header_login a')->current()->nodeValue) {
            return $cookie;
        } else {
            $this->login();
        }
    }
    
    public function login()
    {
        $client->setUri('http://www.amoremcristo.com/login.asp')
                ->setHeaders('Referer', 'http://www.amoremcristo.com/loginadm_main.asp')
                ->setCookieJar()
                ->setParameterPost(array(
                    'go'    => 'now',
                    'email' => $this->config->site->usuario,
                    'senha' => $this->config->site->senha
                ))
                ->setMethod(Zend_Http_Client::POST);
        $response = $client->request();
        if($response->isSuccessful()) {
            shm_put_var($this->shm, 3, serialize($client->getCookieJar()));
        }
    }

    protected function getById($id)
    {
        return $this->db->fetchRow('SELECT * FROM usuario WHERE id = '.$id);
    }
}