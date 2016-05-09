<?php

namespace App\Action\Championship;

use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use phpQuery;
use Carbon\Carbon;
use Thapp\XmlBuilder\XMLBuilder;
use Thapp\XmlBuilder\Normalizer;
use FileSystemCache;
use Stringy\Stringy as S;

final class Last16Action
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
        FileSystemCache::$cacheDir = __DIR__ . '/../../../../cache/tmp';
        $key = FileSystemCache::generateCacheKey('cache-feed_Last16Action', null);
        $data = FileSystemCache::retrieve($key);

        if($data === false)
        {
            $doc = phpQuery::newDocumentFileHTML('http://globoesporte.globo.com/futebol/libertadores/');
            $doc->find('head')->remove();
            $doc->find('meta')->remove();
            $doc->find('noscript')->remove();
            $doc->find('script')->remove();
            $doc->find('style')->remove();
            $doc->find('path')->remove();
            $doc->find('svg')->remove();
            $doc->find('footer')->remove();

            $html = pq('body');

            $data = array(
                'info' => $this->processInfo($html),
                'keys' => $this->processKeys($html),
            );

            FileSystemCache::store($key, $data, 14400);
        }

        $xmlBuilder = new XmlBuilder('root');
        $xmlBuilder->setSingularizer(function ($name) {

            if ('matches' === $name) {
                return 'match';
            }

            if ('teams' === $name) {
                return 'team';
            }

            if ('keys' === $name) {
                return 'key';
            }

            return $name;
        });
        $xmlBuilder->load($data);
        $xml_output = $xmlBuilder->createXML(true);
        $response->write($xml_output);
        $response = $response->withHeader('content-type', 'text/xml');
        return $response;
    }

    public function processInfo($html)
    {
        $doc = phpQuery::newDocument($html);

        return array(
            'title' => (string) S::create('Libertadores 2016')->toUpperCase(),
            'step' => (string) S::create('Oitavas de Final')->toUpperCase(),
            'createdat' => Carbon::now('America/Sao_Paulo')->toDateTimeString()
        );
    }

    public function processKeys($html)
    {
        $data = array();
        $doc = phpQuery::newDocument($html);

        foreach ($doc['section.mata-mata-conteudo div.chave-jogo-espacador'] as $key => $section)
        {
            $pq = pq($section);
            $data[$key] = $this->processKeyMatches($pq->html());
        }

        return $data;
    }

    public function processKeyMatches($html)
    {
        $data = array();
        $doc = phpQuery::newDocument($html);

        foreach ($doc['div.chave-jogo-ida-volta'] as $key => $section)
        {
            $pq = pq($section);
            $data[$key] = $this->processMatches($pq->html());
        }

        return $data;

    }
    public function processMatches($html)
    {
        $data = array();
        $doc = phpQuery::newDocument($html);

        $date = explode('/', substr($doc['div.placar-jogo:eq(0) .placar-jogo-informacoes']->text(), 4, 10));
        $date_1 = $date[2] . '-' . $date[1] . '-' . $date[0] . ' ' . trim(substr($doc['div.placar-jogo:eq(0) .placar-jogo-informacoes']->text(), -6));

        $date = explode('/', substr($doc['div.placar-jogo:eq(1) .placar-jogo-informacoes']->text(), 4, 10));
        $date_2 = $date[2] . '-' . $date[1] . '-' . $date[0] . ' ' . trim(substr($doc['div.placar-jogo:eq(1) .placar-jogo-informacoes']->text(), -6));

        return array(
            'matches' => array(
                array(
                    'date' => $date_1,
                    'local' => $doc['div.placar-jogo:eq(0) div.placar-jogo-informacoes span.placar-jogo-informacoes-local']->text(),
                    'teams' => array(
                        array(
                            'name' => $doc['div.placar-jogo-equipes:eq(0) span.placar-jogo-equipes-nome:eq(0)']->text(),
                            'shield' =>$doc['div.placar-jogo-equipes span.placar-jogo-equipes-item .placar-jogo-equipes-escudo-mandante']->attr('src'),
                        ),
                        array(
                            'name' => $doc['div.placar-jogo-equipes:eq(1) span.placar-jogo-equipes-nome:eq(0)']->text(),
                            'shield' =>$doc['div.placar-jogo-equipes span.placar-jogo-equipes-item .placar-jogo-equipes-escudo-visitante']->attr('src'),
                        ),
                        'score' => array(
                            'active' => false,
                            'home' => '',
                            'visitor' => ''
                        )
                    )
                ),
                array(
                    'date' => $date_2,
                    'local' => $doc['div.placar-jogo:eq(1) div.placar-jogo-informacoes span.placar-jogo-informacoes-local']->text(),
                    'teams' => array(
                        array(
                            'name' => $doc['div.placar-jogo-equipes:eq(1) span.placar-jogo-equipes-nome:eq(0)']->text(),
                            'shield' => $doc['div.placar-jogo-equipes span.placar-jogo-equipes-item .placar-jogo-equipes-escudo-visitante']->attr('src'),
                                                    ),
                        array(
                            'name' => $doc['div.placar-jogo-equipes:eq(0) span.placar-jogo-equipes-nome:eq(0)']->text(),
                            'shield' =>$doc['div.placar-jogo-equipes span.placar-jogo-equipes-item .placar-jogo-equipes-escudo-mandante']->attr('src'),
                        ),
                        'score' => array(
                            'active' => false,
                            'home' => '',
                            'visitor' => ''
                        )
                    )
                )
            )
        );
    }
}