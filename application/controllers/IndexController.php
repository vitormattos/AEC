<?php

class IndexController extends Zend_Controller_Action
{
    public $sessionNamespace;
    protected $pdo;
    protected $db;

    public function init()
    {
        $this->db = new Zend_Db_Adapter_Pdo_Sqlite(array(
            'dbname' => 
                realpath(APPLICATION_PATH . '/../public/sqlite/').'/usuario.sqlite'
        ));
        /* Initialize action controller here */
        $this->sessionNamespace = new Zend_Session_Namespace('Default');
    }

    public function indexAction()
    {
        $this->searchOnline();
    }

    public function searchOnlineAction($recursive = false)
    {
        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/search.asp')
                ->setParameterGet(array(
                    'go'     => 'now',
                    'tb'     => 2,
                    'gender' => 0,
                    'pics'   => 1,
                    'local'  => 1
                ))
                ->setCookieJar($this->sessionNamespace->cookieJar);
        $response = $client->request();
        // verifica a existência do cookie de autenticação
        // se não existir, faz login e refaz a requisição
        if(!$client->getCookieJar() && !$recursive) {
            $this->loginAction();
            $this->searchOnlineAction(1);
        }

        if(!$response->isSuccessful()) return;

        $body = $response->getBody();
        $body = str_replace('&nbsp;', ' ', $body);
        $dom = new Zend_Dom_Query($body);
        $results = $dom->query('.search_results .details_table td');
        $user = array();
        foreach($results as $result) {
            $j = 0;
            foreach($result->childNodes as $node) {
                if($node->nodeType != 1) continue;
                if($j == 0) {
                    $url = $node->firstChild->firstChild->getAttribute('href');                    
                    // id
                    preg_match('{id=([0-9]{1,})}', $url, $id);
                    $id = $id[1];
                    $user[$id] = $this->getUser($id);
                    $user[$id]['url_perfil'] = $url;
                    //return;
                    // status
                    $user[$id]['status'] = $node
                            ->getElementsByTagName('div')->item(1)
                            ->getElementsByTagName('font')->item(0)
                            ->textContent;
                    $this->save($user[$id], $id);
                    return;
                    //Zend_Debug::dump(array_keys($user[$id]));
                } elseif ($j == 2) {
                    // ultimo acesso
                    $user[$id]['ultimo_acesso'] = $node->getElementsByTagName('div')->item(1)->textContent;
                    $user[$id]['ultimo_acesso'] = explode(':', $user[$id]['ultimo_acesso']);
                    $user[$id]['ultimo_acesso'] = trim($user[$id]['ultimo_acesso'][1]);
                    $user[$id]['ultimo_acesso'] = DateTime::createFromFormat('d/m/Y', $user[$id]['ultimo_acesso']);
                    $user[$id]['ultimo_acesso'] = $user[$id]['ultimo_acesso']->format('Y-m-d');
                }
                $j++;
            }
        }
        Zend_Debug::dump($user);
    }
    
    protected function save($usuario, $id) {
        $this->createTable($usuario);
        $insert['usuario']['id'] = $id;
        foreach($usuario as $key => $value) {
            if($key == 'textos') {
                foreach($value as $chave => $valor) {
                    $insert['usuario'][$chave]=$valor;
                }
            } elseif($key == 'denominacao' || is_array($value)) {
                //$create .= "    {$key}_id INTEGER,\n";
                $this->db->fetchAll("SELECT id FROM $key where id = ");
            } else {
                $insert['usuario'][$key]=$value;
            }
        }
        if($this->db->fetchAll('SELECT id FROM usuario where id = '.$id)) {
            unset($insert['usuario']['id']);
            $this->db->update('usuario', $insert['usuario']);
        } else {
            $this->db->insert('usuario', $insert['usuario']);
        }
    }
    
    protected function createTable($usuario) {
        $create = "CREATE TABLE IF NOT EXISTS usuario(\n";
        $create_sub = '';
        $create .= "    id INTEGER PRIMARY KEY,\n";
        foreach($usuario as $key => $value) {
            if($key == 'textos') {
                foreach($value as $chave => $valor) {
                    $create .= "    $chave TEXT,\n";
                }
            } elseif($key == 'denominacao') {
                $create .= "    {$key}_id INTEGER,\n";

                $create_sub = "CREATE TABLE IF NOT EXISTS $key(\n";
                $create_sub.= "    id INTEGER PRIMARY KEY,\n";
                $create_sub.= "    $key varchar(255)\n";
                $create_sub.= ");\n";
                $this->db->query($create_sub);
            } elseif(is_array($value)) {
                $create .= "    {$key}_id INTEGER,\n";

                $create_sub = "CREATE TABLE IF NOT EXISTS $key(\n";
                $create_sub.= "    id INTEGER PRIMARY KEY,\n";
                $create_sub.= "    $key varchar(255)\n";
                $create_sub.= ");\n";
                $this->db->query($create_sub);
            } else {
                $create .= "    $key varchar(255),\n";
            }
        }
        $create = trim($create, ",\n");
        $create .= "\n);";
        $ok = $this->db->query($create);
    }


    protected function slug($var) {
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

    protected function getUser($id) {
        // fuma bebe
        //$id = 2098045;
        // varias denominações
        //$id = 380885;
        // eu
        $id = 232785;
        // gringo
        //$id = 2340755;
        //$id = 2246224;
        $client = new Zend_Http_Client();

        $client->setUri('http://www.amoremcristo.com/profile_view.asp?id='.$id);
        $response = $client->request();

        $dir = '';
        $strlen = strlen($id);
        for($k = $strlen-5; $k >= 0 ; $k--) {
            $dir = substr($id, $k-$strlen, 1).'/'.$dir;
        }
        $user['url_thumb'] = 'http://images.amoremcristo.com/images/usuarios_thumbs'.
            str_repeat('/0', (12-strlen($dir))/2).'/'.$dir.
            'usr'.$id.'t1.jpg';

        $dir = realpath(APPLICATION_PATH . '/../public/').'/img/'.$dir;
        if(!file_exists($dir.$id.'t.jpg')) {
            $shm = shm_attach(12356, 524288);
            shm_put_var($shm, 1, array());
            $pilha = @shm_get_var($shm, 1);
            $ignore = @shm_get_var($shm, 2);
            if($pilha && is_array($pilha)) {
                reset($pilha);
                while(count($pilha)>=199) {
                    unset($pilha[key($pilha)]);
                    unset($ignore[key($pilha)]);
                }
            }
            $pilha[$id] = $user['url_thumb'];
            shm_put_var($shm, 1, $pilha);
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
                    case 'estado_civil_de_quem_eu_busco':
                    case 'a_frequencia_na_igreja_que_mais_se_encaixa_no_meu_perfil_e':
                    case 'gostaria_que_a_pessoa_que_busco_fosse_de_uma_das_seguintes_denominacoes':
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
                $user['tem_filhos'] = $value[0];
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

    public function loginAction()
    {
        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/login.asp')
                ->setHeaders('Referer', 'http://www.amoremcristo.com/loginadm_main.asp')
                ->setCookieJar()
                ->setParameterPost(array(
                    'go'    => 'now',
                    'email' => 'vitor.mattos@gmail.com',
                    'senha' => '140784'
                ))
                ->setMethod(Zend_Http_Client::POST);
        $response = $client->request();

        $this->sessionNamespace->cookieJar = $client->getCookieJar();
    }


}

