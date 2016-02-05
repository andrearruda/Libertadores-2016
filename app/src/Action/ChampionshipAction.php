<?php
namespace App\Action;

use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use phpQuery;
use Carbon\Carbon;

final class ChampionshipAction
{
    private $view;
    private $logger;

    public function __construct(Twig $view, LoggerInterface $logger)
    {
        $this->view = $view;
        $this->logger = $logger;
    }

    public function dispatch(Request $request, Response $response, $args)
    {

        $doc = phpQuery::newDocumentFileHTML('http://globoesporte.globo.com/sp/futebol/campeonato-paulista/');
        $doc->find('head')->remove();
        $doc->find('meta')->remove();
        $doc->find('noscript')->remove();
        $doc->find('script')->remove();
        $doc->find('style')->remove();
        $doc->find('path')->remove();
        $doc->find('svg')->remove();
        $doc->find('footer')->remove();

        $html = pq('#container-para-tabela-simulador-ou-navegacao-js');

        $data = array(
            'info' => $this->processInfo(),
            'groups' => $this->processGroups($html),
            'games' => array()
        );

        var_dump($data);
        die;

/*        $this->logger->info("Home page action dispatched");
        
        $this->view->render($response, 'home.twig');
        return $response;*/
    }

    public function processInfo()
    {
        return array(
            'createdat' => Carbon::now()->toDateTimeString(),
            ''
        );
    }


    public function processGroups($html)
    {
        return null;
    }
}

