<?php

declare(strict_types=1);

namespace App\Controller\API;

use App\Analyzer\MainAnalyzer;
use App\Analyzer\Oscillator\Context\ContextOscillator;
use App\Analyzer\Oscillator\MACD;
use App\Analyzer\Oscillator\RSI;
use App\Analyzer\Oscillator\Stochastic;
use App\Service\Cache\CacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ApiController extends AbstractController
{
    public function __construct(
        private readonly MainAnalyzer        $analyzer,
        private readonly SerializerInterface $serializer,
        private readonly CacheService        $cacheService
    )
    {
    }

    #[Route(path: '/api/', name: 'api', methods: 'GET')]
    public function action(Request $request): Response
    {
        $from = ($request->get('from') * 1000) ?? (new \DateTime('25 days ago'))->getTimestamp() * 1000;
        $to = ($request->get('to') * 1000) ?? (new \DateTime('tomorrow'))->getTimestamp() * 1000;
        $timeframe = $request->get('timeframe') ?? '15m';
        $symbol = $request->get('symbol') ?? 'BNBUSDT';

        $this->analyzer->addOscillator(new Stochastic());
        $this->analyzer->addOscillator(new MACD());
        $this->analyzer->addOscillator(new RSI());
        $this->analyzer->addOscillator(new ContextOscillator());

        $result = $this->analyzer->analyze($symbol, $timeframe, (int)$from, (int)$to);

        //$this->cacheService->hSet(
        //    'crypto.analysis.basic',
        //    '1',
        //    $this->serializer->serialize($result, 'json')
        //);

        return new JsonResponse($this->serializer->serialize($result, 'json'), json: true);
    }

}
