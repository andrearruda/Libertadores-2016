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

final class StepGroupAction
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
        $key = FileSystemCache::generateCacheKey('cache-feed', null);
        $data = FileSystemCache::retrieve($key);

        if($data === false)
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
                'info' => $this->processInfo($html),
                'groups' => $this->processGroups($html),
                'matches' => $this->processMatches($html)
            );

            FileSystemCache::store($key, $data, 1800);
        }

        $xmlBuilder = new XmlBuilder('root');
        $xmlBuilder->setSingularizer(function ($name) {
            if ('teams' === $name) {
                return 'team';
            }

            if ('groups' === $name) {
                return 'group';
            }

            if ('matches' === $name) {
                return 'match';
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
            'title' => (string) S::create('Paulistão 2016')->toUpperCase(),
            'step' => (string) S::create('Primeira fase')->toUpperCase(),
            'round' => $doc->find('.lista-de-jogos .tabela-navegacao .tabela-navegacao-seletor')->text(),
            'createdat' => Carbon::now('America/Sao_Paulo')->toDateTimeString()
        );
    }

    public function processGroups($html)
    {
        $data = array();
        $doc = phpQuery::newDocument($html);

        foreach($doc['section.section-container'] as $key => $section)
        {
            $pq = pq($section);

            $teams = array();
            for($i = 0; $i < 5; $i++)
            {
                $teams[] = array(
                    'position' => $pq->find('table.tabela-times tbody tr:eq(' . $i . ') td:eq(0)')->text(),
                    'name' => $pq->find('table.tabela-times tbody tr:eq(' . $i . ') td:eq(1) strong')->text(),
                    'initials' => $pq->find('table.tabela-times tbody tr:eq(' . $i . ') td:eq(1) span')->text(),
                    'variation' => array(
                        'quantity' => trim($pq->find('table.tabela-times tbody tr:eq(' . $i . ') td:eq(2) span')->text()),
                        'indication' => str_replace('tabela-icone tabela-icone-', '', $pq->find('table.tabela-times tbody tr:eq(' . $i . ') td:eq(2) span.tabela-icone')->attr('class'))
                    ),
                    'info' => array(
                        'points' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(0)')->text(),
                        'performance' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(8)')->text(),
                        'matches' => array(
                            'victories' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(2)')->text(),
                            'draws' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(3)')->text(),
                            'defeats' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(4)')->text(),
                        ),
                        'goals' => array(
                            'pro' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(5)')->text(),
                            'against' => $pq->find('table.tabela-pontos tbody tr:eq(' . $i . ') td:eq(6)')->text(),
                        )
                    )
                );
            }


            $data[$key] = array(
                'name' => $pq->find('header h2')->text(),
                'teams' => $teams
            );
        }

        return $data;
    }

    public function processMatches($html)
    {
        $data = array();
        $doc = phpQuery::newDocument($html);

        foreach($doc['ul.lista-de-jogos-conteudo li.lista-de-jogos-item'] as $key => $section)
        {
            $pq = pq($section);

            $date = explode('/', substr($pq->find('.placar-jogo-informacoes')->text(), 4, 10));
            $date = $date[2] . '-' . $date[1] . '-' . $date[0] . ' ' . substr($pq->find('.placar-jogo-informacoes')->text(), -5);

            $data[$key] = array(
                'date' => $date,
                'local' => $pq->find('.placar-jogo-informacoes span.placar-jogo-informacoes-local')->text(),
                'teams' => array(
                    'home' => array(
                        'name' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-mandante .placar-jogo-equipes-nome')->text(),
                        'initials' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-mandante .placar-jogo-equipes-sigla')->text(),
                        'shield' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-escudo-mandante')->attr('src'),
                    ),
                    'visitor' => array(
                        'name' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-visitante .placar-jogo-equipes-nome')->text(),
                        'initials' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-visitante .placar-jogo-equipes-sigla')->text(),
                        'shield' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-escudo-visitante')->attr('src'),
                    )
                ),
                'score' => array(
                    'active' => true,
                    'home' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-placar-mandante')->text(),
                    'visitor' => $pq->find('.placar-jogo-equipes .placar-jogo-equipes-placar-visitante')->text()
                )
            );

            if(strlen($data[$key]['score']['home']) == 0 && strlen($data[$key]['score']['visitor']) == 0)
            {
                $data[$key]['score']['active'] = false;
            }
        }

        return $data;
    }
}