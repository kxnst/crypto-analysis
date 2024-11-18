<?php

declare(strict_types=1);

namespace App\Command;

use App\Analyzer\Oscillator\InternalAnalyzerResult;
use App\Service\Analysis\AnalysisService;
use App\Service\Analysis\LocalResult;
use App\Service\Cache\CacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(self::COMMAND_NAME)]
class AnalyseCommand extends Command
{
    public const COMMAND_NAME = 'app:analyse';

    public function __construct(
        private readonly AnalysisService $analysisService,
        private readonly CacheService $cacheService,
        private readonly SerializerInterface $serializer
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('memory', default: 15)
            ->addArgument('sensitivity', default: 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $memory = (int)$input->getArgument('memory');
        $sensitivity = (int)$input->getArgument('sensitivity');

        $cacheData = $this->cacheService->hGet('crypto.analysis.basic', '1');
        $internalResult = $this->serializer->deserialize($cacheData, InternalAnalyzerResult::class, 'json');
        [$result, $totalResult] = $this->analysisService->analyse($internalResult, $memory, $sensitivity);

        foreach ($result as $oscillator => $values) {
            $output->writeln($oscillator);
            foreach ($values as $tag => $results) {
                $tagText = $tag ?: 'No context';
                $output->writeln("----{$tagText}");
                foreach ($results as $localResult => $percentage) {
                    $text = match ($localResult) {
                        LocalResult::RESULT_COMPLETED => 'Success',
                        LocalResult::RESULT_MIXED => 'Mixed',
                        LocalResult::RESULT_UNKNOWN => 'False signal',
                        default => 'Failed signal',
                    };

                    $output->writeln("--------$text: $percentage");
                }
            }
        }

        $output->writeln('');
        $output->writeln('Total results');
        $totalSignals = array_sum($totalResult);
        foreach ($totalResult as $direction => $count) {
            $text = match ($direction) {
                LocalResult::RESULT_COMPLETED => 'Success',
                LocalResult::RESULT_MIXED => 'Mixed',
                LocalResult::RESULT_UNKNOWN => 'False signal',
                default => 'Failed signal',
            };

            $percentage = round($count / $totalSignals * 100, 2);
            $output->writeln("$text - $count ($percentage%)");
        }
        return self::SUCCESS;
    }
}
