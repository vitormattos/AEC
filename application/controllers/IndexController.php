<?php

class IndexController extends Zend_Controller_Action
{
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
    
    /**
     * configurações extras do sistema
     * @var Zend_Config
     */
    protected $config = array();

    public function init()
    {
        $this->aec = new Robot_Aec();
        $this->config = new Zend_Config_Ini(
            APPLICATION_PATH.'/configs/'.
            $this->getInvokeArg('bootstrap')->getOption('senhas')
        );
        $this->db = new Zend_Db_Adapter_Pdo_Mysql(array(
            'host' => $this->config->database->host,
            'dbname' => $this->config->database->dbname,
            'username' => $this->config->database->username,
            'password' => $this->config->database->password
        ));
        $this->aec->createTable();
    }

    public function indexAction()
    {
        //$this->searchOnline();
    }

    public function lastAction()
    {
        $result = $this->db->fetchAll("
            SELECT usuario.id, apelido, url_perfil, `status`, estado_civil, idade,
                   url_thumb, altura, peso, denominacao.denominacao, profissao,
                   formacao, quer_ter_filhos, updated, created, ultimo_acesso
              FROM usuario
              LEFT JOIN denominacao ON denominacao.id = usuario.denominacao_id
              LEFT JOIN (SELECT max(ultimo_acesso) AS ultimo_acesso,
                                usuario_id
                          FROM ultimo_acesso
                         GROUP BY usuario_id
                        ) ultimo_acesso
                ON ultimo_acesso.usuario_id = usuario.id
             WHERE tem_filhos = 'nao'
             /*AND quer_ter_filhos = 'não quero ter filhos'*/
             AND sexo = 'Feminino'
             AND estado_civil IN ('Solteiro(a)', 'Viúvo(a)')
             AND cidade = 'Rio De Janeiro'
             AND idade <= 35
             AND idade >= 23
             AND url_thumb IS NOT NULL
             ORDER BY status DESC, updated DESC
             LIMIT 30");
        foreach($result as $key => $field) {
            $dir = '';
            $strlen = strlen($field['id']);
            for($k = $strlen-4; $k >= 0 ; $k--) {
                $dir = substr($field['id'], $k-$strlen, 1).'/'.$dir;
            }            
            for($i=1;$i<=5;$i++) {
                $img = realpath(APPLICATION_PATH . '/../public/').'/img/fotos/'.$dir.$field['id'].'p'.$i.'.jpg';
                if(file_exists($img)) {
                    $change_date = date("F d Y H:i:s.", filemtime($img));
                    $result[$key]['last_change_img'] = $change_date;
                    if($change_date < $field['updated']) {
                        $this->aec->runBackground(
                            'Robot_Aec',
                            'getImagem',
                            array($field['id'], true)
                        );
                        $result[$key]['img_updated'] = 'sim';
                    } else {
                        $result[$key]['img_updated'] = 'não';
                    }
                    $result[$key]['img_url'][] = array(
                        'url' => '/img/fotos/'.$dir.$field['id'].'p'.$i.'.jpg',
                        'alt' => $field['apelido']
                    );
                    $result[$key]['img_updated'] = 'sim';
                } elseif($i==1) {
                    $this->aec->runBackground(
                        'Robot_Aec',
                        'getImagem',
                        array($field['id'], true)
                    );
                    $result[$key]['img_updated'] = 'sim';
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
            return;
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
        if(!$result || $this->getRequest()->getParam('update') == 1) {
            // joga para background
            $this->aec->runBackground('Robot_Aec', 'getAndSave', array($id));
            $this->aec->runBackground('Robot_Aec', 'getImagem', array($id, true));
            if($this->getRequest()->getParam('update') == 1) {
                $this->redirect('/index/perfil/?id='.$id);
                return;
            } else {
                $this->view->headMeta()->appendHttpEquiv('refresh','8');
                return;
            }
        }
        $dir = '';
        $strlen = strlen($result['id']);
        for($k = $strlen-4; $k >= 0 ; $k--) {
            $dir = substr($result['id'], $k-$strlen, 1).'/'.$dir;
        }
        for($i=1;$i<=5;$i++) {
            $img = realpath(APPLICATION_PATH . '/../public/').'/img/fotos/'.$dir.$result['id'].'p'.$i.'.jpg';
            if(file_exists($img)) {
                
                $change_date = date("F d Y H:i:s.", filemtime($img));
                $result['last_change_img'] = $change_date;
                if($change_date < $result['updated']) {
                    $this->aec->runBackground(
                        'Robot_Aec',
                        'getImagem',
                        array($result['id'], true)
                    );
                    $result['img_updated'] = 'sim';
                } else {
                    $result['img_updated'] = 'não';
                }
                $result['img_url'][] = array(
                    'url' => '/img/fotos/'.$dir.$result['id'].'p'.$i.'.jpg',
                    'alt' => $result['apelido']
                );
            } elseif($i==1) {
                $this->aec->runBackground('Robot_Aec', 'getImagem', array($result['id']));
            } else break;
        }
        $this->view->user = $result;
        try {
            $this->aec->lerPaginaMensagem('recebidas');
        } catch (Exception $exc) { }
        
        $mensagens = $this->db->fetchAll("
            SELECT *
              FROM mensagem
             WHERE usuario_id = $id
                OR remetente_id = $id
             ORDER BY data_envio
        ");
        $this->view->mensagens = $mensagens;
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
                    'message' => iconv("UTF-8", "ISO-8859-1", $mensagem)
                 ))
                ->setParameterGet(array(
                    'id'      => $id
                ))
                ->setHeaders('Referer', 'http://www.amoremcristo.com/msgsend.asp?id='.$id)
                ->setMethod(Zend_Http_Client::POST)
                ->setCookieJar($this->aec->getCookie());
        $response = $client->request('POST');
        
        if(!is_a($response, 'Zend_Http_Response')) return;

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
            $this->aec->login();
            goto a;
        }
        $this->aec->lerPaginaMensagem('enviadas');
    }

    public function inboxAction()
    {
        $this->view->mensagens = array();
        foreach(array('recebidas', 'enviadas') as $tipo) {
            $this->aec->lerPaginaMensagem($tipo, 1);
            foreach($this->aec->mensagens as $id => $mensagem) {
                $dir = '';
                $strlen = strlen($mensagem['usuario_id']);
                for($k = $strlen-4; $k >= 0 ; $k--) {
                    $dir = substr($mensagem['usuario_id'], $k-$strlen, 1).'/'.$dir;
                }
                $img = realpath(APPLICATION_PATH . '/../public/').'/img/fotos/'.$dir.$mensagem['usuario_id'].'t.jpg';
                if(file_exists($img)) {
                    $mensagem['img_src'] = '/img/fotos/'.$dir.$mensagem['usuario_id'].'t.jpg';
                } else {
                    $this->aec->runBackground(
                        'Robot_Aec',
                        'getImagem',
                        array($mensagem['usuario_id'])
                    );
                }
                $this->aec->mensagens[$id] = $mensagem;
            }
            $this->view->mensagens[$tipo] = $this->aec->mensagens;
            $this->aec->mensagens = array();
        }
    }
}

