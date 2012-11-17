<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        $this->searchOnline();
    }

    private function searchOnline() {
        // action body
        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/search.asp?go=now&tb=2&gender=0&pics=1&local=1');
        $response = $client->request();
        Zend_Debug::dump($response->getHeaders());
    }

    private function loginAction() {
        // action body
        $client = new Zend_Http_Client();
        $client->setUri('http://www.amoremcristo.com/login.asp');
        $client->setParameterPost(array(
            'go'    => 'now',
            'email' => 'vitor.mattos@gmail.com',
            'senha' => '0i0l0j0e'
        ));
        $response = $client->request('POST');
        Zend_Debug::dump($response->getHeaders());
    }
}