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
        $this->createTable();
        
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
        
        if(!is_a($response, 'Zend_Http_Response')) return;
        
        if(!$response->isSuccessful()) return;

        $dir = '';
        $strlen = strlen($id);
        for($k = $strlen-5; $k >= 0 ; $k--) {
            $dir = substr($id, $k-$strlen, 1).'/'.$dir;
        }

        $dir = realpath(APPLICATION_PATH . '/../public/').'/img/fotos/'.$dir;
        if(!file_exists($dir.$id.'t.jpg')) {
            $this->runBackground('Robot_Aec', 'getImagem', array($id, true));
        } else {
            $user['url_thumb'] = 'http://images.amoremcristo.com/images/usuarios_thumbs'.
                str_repeat('/0', (12-strlen($dir))/2).'/'.$dir.
                'usr'.$id.'t1.jpg';
            $change_date = date("F d Y H:i:s.", filemtime($dir.$id.'t.jpg'));
            if($change_date < $user['updated']) {
                $this->runBackground('Robot_Aec', 'getImagem', array($user['id'], true));
            }
        }

        $body = $response->getBody();
        $body = str_replace('&nbsp;', ' ', $body);
        $dom = new Zend_Dom_Query($response->getBody());
        $user = array();
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
                    .' >> '.realpath(APPLICATION_PATH . '/../scripts/').'/log';
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
        try {
            $response = $client->request();
        } catch (Exception $exc) { return; }

        if(!is_a($response, 'Zend_Http_Response')) return;

        if(!$response->isSuccessful()) return;

        $body = str_replace('&nbsp;', ' ', $response->getBody());
        $dom = new Zend_Dom_Query($body);
        // verifica se está autenticado
        // senão, faz login e refaz a requisição
        if($dom->query('.header_login a')->count() < 2 && !isset($recursive)) {
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
            $ids[] = $id;
        }
        // Apenas pega dados do usuário se ele não existir
        $existe = $this->getById($ids);
        foreach($existe as $usuario) {
            if(!$usuario['apelido']) {
                // joga para background
                $this->runBackground('Robot_Aec', 'getAndSave', array($usuario['id']));
            }
        }
        if(count($users_online)) {
            $this->db->update(
                'usuario',
                array('status' => 'Offline'),
                'id NOT IN ('.implode(', ', $users_online).')'
            );
        }
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

        if(!is_a($response, 'Zend_Http_Response')) return;
        
        $dom = new Zend_Dom_Query($response->getBody());
        if($dom->query('.header_login a')->current()->nodeValue) {
            return $cookie;
        } else {
            $this->login();
        }
    }
    
    public function login()
    {
        $client = new Zend_Http_Client();
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

        if(!is_a($response, 'Zend_Http_Response')) return;

        if($response->isSuccessful()) {
            shm_put_var($this->shm, 3, serialize($client->getCookieJar()));
        }
    }

    protected function getById($id)
    {
        if(is_array($id)) {
            return $this->db->fetchAll(
                'SELECT * FROM usuario WHERE id IN('.  implode(', ', $id).')'
            );
        } else {
            return $this->db->fetchRow('SELECT * FROM usuario WHERE id = '.$id);
        }
    }
    
    /**
     * Lê as mensagens da caixa de enviadas ou recebidas
     * 
     * @param string $tipo_pagina recebidas enviadas
     * @param int $numero_pagina
     * @return int Número da próxima página
     */
    public function lerPaginaMensagem($tipo_pagina, $numero_pagina = 1)
    {
        $client = new Zend_Http_Client();
        a:
        $client->setUri('http://www.amoremcristo.com/loginadm_emails.asp')
                ->setParameterGet(array(
                    's' => $tipo_pagina == 'recebidas' ? 'r' : 's',
                    'p' => $numero_pagina
                ))
                ->setHeaders('Referer', 'http://www.amoremcristo.com/loginadm_emails.asp')
                ->setCookieJar($this->getCookie());
        try {
            $response = $client->request();
        } catch (Exception $exc) {
            if(!isset($recursive)) {
                $this->login();
                $client->resetParameters();
                $recursive = true;
                goto a;
            }
        }
        
        if(!is_a($response, 'Zend_Http_Response')) return;

        if(!$response->isSuccessful()) return;

        $body = str_replace('&nbsp;', ' ', $response->getBody());
        $dom = new Zend_Dom_Query($body);
        // verifica se está autenticado
        // senão, faz login e refaz a requisição
        if($dom->query('.header_login a')->count() < 2 && !isset($recursive)) {
            $this->login();
            $client->resetParameters();
            $recursive = true;
            goto a;
        }

        // ID do usuário logado
        $meu_id = $dom->query('.loginadm_wrapper .left_menu a img')->current();
        $meu_id = $meu_id->C14N();
        preg_match('{/usr([0-9]*)}', $meu_id, $meu_id);
        $meu_id = $meu_id[1];
        
        $td = new Zend_Dom_Query();
        $results = $dom->query('.details_table tr.even, .details_table tr.odd');
        foreach($results as $result) {
            $td->setDocument($result->C14N());
            $campos = $td->query('td');
            $i = 0;
            foreach($campos as $campo) {
                switch($i) {
                    case 0:
                        preg_match('{value="([0-9]*)"}', $campo->C14N(), $mensagem_id);
                        if(!$mensagem_id) continue 3;
                        $mensagem_id = $mensagem_id[1];
                        break;
                    case 1:
                        // apelido
                        $apelido = $campo->getElementsByTagName('font')->item(0)->nodeValue;
                        // usuario_id
                        preg_match('{id=([0-9]*)}', $campo->C14N(), $usuario_id);
                        if(!isset($usuario_id[1])) {
                            preg_match('{/usr([0-9]*)t1.jpg"}', $campo->C14N(), $usuario_id);
                            // usuário inativo
                            if(!isset($usuario_id[1])) {
                                continue 3;
                            }
                        }
                        $usuario_id = $usuario_id[1];
                        break;
                    case 2:
                        $read = strpos($campo->C14N(), 'color:#bdbcbc;')?1:0;
                        break;
                    case 3:
                        $data = $campo->nodeValue;
                        break;
                }
                $i++;
            }
            $this->mensagens[$mensagem_id] = array(
                'id' => $mensagem_id,
                'remetente_id' => $tipo_pagina == 'recebidas' ? $usuario_id : $meu_id,
                'usuario_id' => $usuario_id,
                'data_envio' => $data,
                'status' => $read,
                'apelido' => $apelido
            );
        }
        $this->saveMessages($this->mensagens);
        return $this->paginate($dom, $numero_pagina);
    }
    
    /**
     * retorna a próxima página ou null se não houver próxima
     * 
     * @param Zend_Dom_Query $dom
     */
    private function paginate($dom, $pagina_atual)
    {
        $results = $dom->query('.paging ul>li');
        $achou = false;
        foreach($results as $result) {
            if($result->getAttribute('class') == 'paging_current') {
                $achou = true;
            } elseif($achou){
                if($result->getAttribute('class') == 'paging_link')
                if(is_numeric($result->nodeValue))
                if($result->nodeValue > $pagina_atual) {
                    return $result->nodeValue;
                }
                break;
            }
        }
    }
    
    private function saveMessages($mensagens)
    {
        if(count($mensagens)) {
            $results = $this->db->fetchAll(
                "SELECT id, status FROM mensagem WHERE id IN (" .
                    implode(', ', array_keys($mensagens)) .
                ")"
            );
        }
        if($results)
        foreach($results as $result) {
            if(!$result['status'] && $mensagens[$result['id']]['status']) {
                $this->db->update(
                    'mensagem',
                    array('status' => $mensagens[$result['id']]['status']),
                    "id = {$result['id']}"
                );
            }
            unset($mensagens[$result['id']]);
        }
        foreach($mensagens as $mensagem) {
            unset($mensagem['apelido']);
            $this->db->insert('mensagem', $mensagem);
            $this->getMessage($mensagem['id'], $mensagem['remetente_id']);
        }
    }

    private function getMessage($mensagem_id, $remetente_id)
    {
        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/msgread.asp')
                ->setParameterGet(array(
                    'id' => $mensagem_id,
                    'ns' => 7,
                    'is' => $remetente_id
                ))
                ->setHeaders('Referer', 'http://www.amoremcristo.com/loginadm_emails.asp')
                ->setCookieJar($this->getCookie());
        $response = $client->request();
        
        if(!is_a($response, 'Zend_Http_Response')) return;

        $body = str_replace('&nbsp;', ' ', $response->getBody());

        $dom = new Zend_Dom_Query($body);
        $results = $dom->query('.message_text');
        if($results->count()){
            $this->db->update(
                'mensagem',
                array('mensagem' => iconv("UTF-8", "ISO-8859-1", $results->current()->nodeValue)),
                "id = $mensagem_id"
            );
        }
    }
    
    public function getImagem($id, $force_update = false)
    {
        $dir = '';
        $strlen = strlen($id);
        for($k = $strlen-4; $k >= 0 ; $k--) {
            $dir = substr($id, $k-$strlen, 1).'/'.$dir;
        }
        $img_dir = realpath(APPLICATION_PATH.'/../public/img/fotos/');
        if(!is_dir($img_dir.'/'.$dir)) mkdir($img_dir.'/'.$dir, 0777, true);
        if(file_exists($img_dir.'/'.$dir.$id.'t.jpg') &&
           file_exists($img_dir.'/'.$dir.$id.'p1.jpg') &&
           !$force_update) continue;

        $url = $this->getUrlThumb($id);

        $img = @file_get_contents($url);
        if($img) {
            echo $img_dir.'/'.$dir.$id."t.jpg\n";
            file_put_contents($img_dir.'/'.$dir.$id.'t.jpg', $img);
            $this->save(array('url_thumb' => $url), $id);
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
    }

    public function createTable()
    {
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS ultimo_acesso(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                ultimo_acesso varchar(255)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS denominacao(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                denominacao varchar(255)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS estado_civil_de_quem_eu_busco(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                estado_civil_de_quem_eu_busco varchar(255)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS usuario_estado_civil_de_quem_eu_busco(
                estado_civil_de_quem_eu_busco_id INTEGER,
                usuario_id INTEGER,
                PRIMARY KEY(estado_civil_de_quem_eu_busco_id, usuario_id)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS tenho_maior_interesse_em_pessoas_das_seguintes_formacoes(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                tenho_maior_interesse_em_pessoas_das_seguintes_formacoes varchar(255)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS usuario_tenho_maior_interesse_em_pessoas_das_seguintes_formacoes(
                tenho_maior_interesse_em_pessoas_das_seguintes_formacoes_id INTEGER,
                usuario_id INTEGER,
                PRIMARY KEY(tenho_maior_interesse_em_pessoas_das_seguintes_formacoes_id, usuario_id)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS denominacao_desejada(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                denominacao_desejada varchar(255)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS usuario_denominacao_desejada(
                denominacao_desejada_id INTEGER,
                usuario_id INTEGER,
                PRIMARY KEY(denominacao_desejada_id, usuario_id)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS frequencia_igreja_desejada(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                frequencia_igreja_desejada varchar(255)
            );";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS usuario_frequencia_igreja_desejada(
                frequencia_igreja_desejada_id INTEGER,
                usuario_id INTEGER,
                PRIMARY KEY(frequencia_igreja_desejada_id, usuario_id)
            );
        ";
        $create_sub[]= "
            CREATE TABLE IF NOT EXISTS `mensagem` (
              `id` int(11) NOT NULL,
              `remetente_id` int(11) NOT NULL,
              `usuario_id` int(11) NOT NULL,
              `data_envio` varchar(40) NOT NULL,
              `status` smallint(6) NOT NULL,
              `mensagem` text
            )
        ";
        foreach($create_sub as $create) {
            $this->db->query($create);
        }
        $create = "
            CREATE TABLE IF NOT EXISTS usuario(
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                url_thumb varchar(255),
                apelido varchar(255),
                sexo varchar(255),
                idade varchar(255),
                pais varchar(255),
                estado varchar(255),
                cidade varchar(255),
                altura varchar(255),
                peso varchar(255),
                tipo_fisico varchar(255),
                tom_de_pele varchar(255),
                olhos varchar(255),
                estado_civil varchar(255),
                formacao varchar(255),
                profissao varchar(255),
                nacionalidade varchar(255),
                tem_filhos varchar(255),
                quer_ter_filhos varchar(255),
                fuma varchar(255),
                bebe varchar(255),
                denominacao_id INTEGER,
                importancia_de_religiao_para_mim varchar(255),
                meu_estilo varchar(255),
                frequencia_na_igreja varchar(255),
                considero_me_uma_pessoa TEXT,
                estou_a_procura_de TEXT,
                meus_filmes_favoritos TEXT,
                minhas_musicas_favoritas TEXT,
                o_que_eu_gosto_de_fazer TEXT,
                sexo_procurado varchar(255),
                localidade varchar(255),
                acho_que_a_faixa_etaria_que_mais_se_encaixa_ao_meu_perfil_e varchar(255),
                busco_uma_pessoa_com_altura varchar(255),
                acho_que_o_peso_ideal_de_quem_eu_busco_deve_ser varchar(255),
                o_tipo_fisico_de_quem_busco_deve_ser varchar(255),
                busco_uma_pessoa_com_tom_de_pele varchar(255),
                acho_que_os_olhos_ideais_de_quem_eu_busco_devem_ser varchar(255),
                desejada_filhos varchar(255),
                busco_uma_pessoa_que_tenha_a_seguinte_relacao_a_filhos_no_futuro varchar(255),
                em_relacao_a_fumar_quero_alguem_que varchar(255),
                em_relacao_a_beber_busco_alguem_que varchar(255),
                idealmente_a_pessoa_que_busco_deve_ter_o_seguinte_estilo varchar(255),
                url_perfil varchar(255),
                status varchar(255),
                ultimo_acesso TEXT,
                updated TEXT,
                created TEXT
            );
        ";
        $this->db->query($create);
    }
    
    public function getUrlThumb($id)
    {
        $url = '';
        $strlen = strlen($id);
        for($k = $strlen-5; $k >= 0 ; $k--) {
            $url = substr($id, $k-$strlen, 1).'/'.$url;
        }
        return 'http://images.amoremcristo.com/images/usuarios_thumbs'.
            str_repeat('/0', (12-strlen($url))/2).'/'.$url.
            'usr'.$id.'t1.jpg';
    }
}