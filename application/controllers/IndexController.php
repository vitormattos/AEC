<?php

class IndexController extends Zend_Controller_Action
{

    /**
     * Sessão
     * @var Zend_Session
     *
     *
     */
    public $sessionNamespace = null;

    /**
     * Instância do banco
     * @var Zend_Db_Adapter_Pdo_Mysql
     *
     *
     */
    protected $db = null;

    /**
     * instância da classe do robô
     *
     * @var Robot_Aec
     *
     *
     */
    protected $aec = null;

    public function init()
    {
        $this->db = new Zend_Db_Adapter_Pdo_Mysql(array(
            'dbname' => 'aec',
            'username' => 'root',
            'password' => 'root'
        ));
        $this->createTable();
        /*$this->db = new Zend_Db_Adapter_Pdo_Sqlite(array(
            'dbname' => 
                realpath(APPLICATION_PATH . '/../public/sqlite/').'/usuario.sqlite'
        ));*/
        $this->aec = new Robot_Aec();
        /* Initialize action controller here */
        $this->sessionNamespace = new Zend_Session_Namespace('Default');
    }

    public function indexAction()
    {
        //$this->searchOnline();
    }

    public function searchOnlineAction($recursive = false)
    {
        //$this->loginAction();
        $client = new Zend_Http_Client();
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
                    'pics'    => 1,
                    'local'   => 1
                ))
        //$client->setUri('http://www.amoremcristo.com/search.asp?go=now&tb=9&gender=0&fromage=0&toage=35&pais=28&estado=19&cidade=6935&pics=1&local=1')
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
        $this->db->update('usuario', array('status' => 'Offline'));
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
                    // Apenas pega dados do usuário se ele não existir
                    $existe = $this->getById($id);
                    if(!$existe['url_thumb']) {
                        // joga para background
                        $process = realpath(APPLICATION_PATH . '/../scripts/').
                                '/load_url.php --id '.$id
                                .' >> '.realpath(APPLICATION_PATH . '/../scripts/').'/log2';
                        pclose(popen("php $process &", 'r'));
                        $user[$id]['url_perfil'] = $url;
                    }
                    // status
                    $user[$id]['status'] = $node
                            ->getElementsByTagName('div')->item(1)
                            ->getElementsByTagName('font')->item(0)
                            ->textContent;
                } elseif ($j == 2) {
                    // ultimo acesso
                    $user[$id]['ultimo_acesso'] = $node->getElementsByTagName('div')->item(1)->textContent;
                    $user[$id]['ultimo_acesso'] = explode(':', $user[$id]['ultimo_acesso']);
                    $user[$id]['ultimo_acesso'] = trim($user[$id]['ultimo_acesso'][1]);
                    $user[$id]['ultimo_acesso'] = DateTime::createFromFormat('d/m/Y', $user[$id]['ultimo_acesso']);
                    $user[$id]['ultimo_acesso'] = $user[$id]['ultimo_acesso']->format('Y-m-d');
                }
                $this->aec->save($user[$id], $id);
                $j++;
            }
        }
        foreach($user as $id => $u) {
            $dir = '';
            $strlen = strlen($id);
            for($k = $strlen-4; $k >= 0 ; $k--) {
                $dir = substr($id, $k-$strlen, 1).'/'.$dir;
            }
            if(file_exists(realpath(APPLICATION_PATH . '/../public/').'/img/'.$dir.$id.'p1.jpg')) {
                echo 
                '<img src="/img/'.$dir.$id.'p1.jpg"    >';
            }
        }
        //Zend_Debug::dump($user);
    }

    protected function createTable()
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
        if($response->isSuccessful()) {
            $this->sessionNamespace->cookieJar = $client->getCookieJar();
        }
    }

    protected function getById($id)
    {
        return $this->db->fetchRow('SELECT * FROM usuario WHERE id = '.$id);
    }

    public function lastAction()
    {
        $result = $this->db->fetchAll("
            SELECT usuario.id, apelido, url_perfil, `status`, estado_civil, idade,
                   url_thumb, altura, peso, denominacao.denominacao, profissao,
                   formacao, quer_ter_filhos, updated, ultimo_acesso
              FROM usuario
              LEFT JOIN denominacao ON denominacao.id = usuario.denominacao_id
              LEFT JOIN (SELECT max(ultimo_acesso) AS ultimo_acesso,
                                usuario_id
                          FROM ultimo_acesso
                         GROUP BY usuario_id
                        ) ultimo_acesso
                ON ultimo_acesso.usuario_id = usuario.id
             WHERE tem_filhos = 'Não possuo filhos'
             /*AND quer_ter_filhos = 'não quero ter filhos'*/
             AND sexo = 'Feminino'
             AND estado_civil IN ('Solteiro(a)', 'Viúvo(a)')
             AND cidade = 'Rio De Janeiro'
             AND idade <= 35
             ORDER BY status DESC, ultimo_acesso DESC, updated DESC
             LIMIT 15");
        foreach($result as $key => $field) {
            $dir = '';
            $strlen = strlen($field['id']);
            for($k = $strlen-4; $k >= 0 ; $k--) {
                $dir = substr($field['id'], $k-$strlen, 1).'/'.$dir;
            }            
            for($i=1;$i<=5;$i++) {
                
                $img = realpath(APPLICATION_PATH . '/../public/').'/img/'.$dir.$field['id'].'p'.$i.'.jpg';
                if(file_exists($img)) {
                    $change_date = date("F d Y H:i:s.", filemtime($img));
                    $result[$key]['last_change_img'] = $change_date;
                    if($change_date < $field['updated']) {
                        $this->aec->pushPilha($field['id'], $field['url_thumb'], true);
                        $result[$key]['img_updated'] = 'sim';
                    } else {
                        $result[$key]['img_updated'] = 'não';
                    }
                    $result[$key]['img_url'][] = array(
                        'url' => '/img/'.$dir.$field['id'].'p'.$i.'.jpg',
                        'alt' => $field['apelido']
                    );
                    $result[$key]['img_updated'] = 'sim';
                } elseif($i==1) {
                    $this->aec->pushPilha($field['id'], $field['url_thumb']);
                    $result['img_updated'] = 'sim';
                } else break;
            }
        }
        $this->view->users = $result;
    }

    public function perfilAction()
    {
        $id = $this->getRequest()->getParam('id');
        if(!$id) {
            $this->view->erro = 'Perfil inválido';
        }
        $result = $this->db->fetchRow("
            SELECT usuario.*, denominacao.denominacao, ultimo_acesso
              FROM usuario
              LEFT JOIN denominacao ON denominacao.id = usuario.denominacao_id
              LEFT JOIN (SELECT max(ultimo_acesso) AS ultimo_acesso,
                                usuario_id
                          FROM ultimo_acesso
                         GROUP BY usuario_id
                        ) ultimo_acesso
                ON ultimo_acesso.usuario_id = usuario.id
             WHERE usuario.id = {$id}");
        if(!$result) {
            // joga para background
            $process = realpath(APPLICATION_PATH . '/../scripts/').
                    '/load_url.php --id '.$id
                    .' >> '.realpath(APPLICATION_PATH . '/../scripts/').'/log2';
            pclose(popen("php $process &", 'r'));
            $this->redirect('/index/perfil/?id='.$id);
            return;
        }
        $dir = '';
        $strlen = strlen($result['id']);
        for($k = $strlen-4; $k >= 0 ; $k--) {
            $dir = substr($result['id'], $k-$strlen, 1).'/'.$dir;
        }            
        for($i=1;$i<=5;$i++) {
            $img = realpath(APPLICATION_PATH . '/../public/').'/img/'.$dir.$result['id'].'p'.$i.'.jpg';
            if(file_exists($img)) {
                
                $change_date = date("F d Y H:i:s.", filemtime($img));
                $result['last_change_img'] = $change_date;
                if($change_date < $result['updated']) {
                    $this->aec->pushPilha($result['id'], $result['url_thumb'], true);
                    $result['img_updated'] = 'sim';
                } else {
                    $result['img_updated'] = 'não';
                }
                $result['img_url'][] = array(
                    'url' => '/img/'.$dir.$result['id'].'p'.$i.'.jpg',
                    'alt' => $result['apelido']
                );
            } elseif($i==1) {
                $this->aec->pushPilha($result['id'], $result['url_thumb']);
            } else break;
        }
        $this->view->user = $result;
    }

    public function sendMessageAction()
    {
        a:
        $id = $this->getRequest()->getParam('id');
        $mensagem = $this->getRequest()->getParam('mensagem');

        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/msgsend.asp')
                ->setParameterPost(array(
                    'go'      => 'now',
                    'subj'    => iconv("UTF-8", "ISO-8859-1", 'Olá!'),
                    'message' => $mensagem
                 ))
                ->setParameterGet(array(
                    'id'      => $id
                ))
                ->setHeaders('Referer', 'http://www.amoremcristo.com/msgsend.asp?id='.$id)
                ->setMethod(Zend_Http_Client::POST)
                ->setCookieJar($this->sessionNamespace->cookieJar);
        $response = $client->request('POST');

        $body = str_replace('&nbsp;', ' ', $response->getBody());
        $dom = new Zend_Dom_Query($body);
        $results = $dom->query('.loginadm_wrapper  .content div');
        $this->view->mensagens = array();
        foreach($results as $result) {
            $this->view->mensagens[] = $result->firstChild->C14N();
        }
        
        // verifica a existência do cookie de autenticação
        // se não existir, faz login e refaz a requisição
        if(!count($this->view->mensagens) && @!$recursive) {
            $recursive = 1;
            $this->loginAction();
            goto a;
        }
    }


}

