<?php
/**
 * Copyright © Orangecat. All rights reserved.
 */
declare(strict_types=1);

namespace Orangecat\IndexerBenchMago\Service\Tool;

use Orangecat\IndexerBench\Model\Export\RunExporter;
use Orangecat\IndexerBench\Model\Run\RunReader;

/**
 * One benchmark run as the Markdown report of indexer-bench:export
 */
class ReportTool extends AbstractReadTool
{
    public const NAME = 'indexer_bench_report';

    /**
     * @param RunReader $runReader
     * @param RunExporter $runExporter
     */
    public function __construct(
        private readonly RunReader $runReader,
        private readonly RunExporter $runExporter
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Returns the full Markdown report of one Indexer Bench benchmark run (environment, metrics, '
            . 'profile), by run ID or the latest run. Read only.';
    }

    /**
     * JSON Schema of the parameters
     *
     * @return array<string,mixed>
     */
    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'run_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Run ID; the latest run when left out',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getInstructions(): string
    {
        return 'Summarise the report unless the admin asks for it whole; the Markdown can be pasted into '
            . 'docs/benchmarks/.';
    }

    /**
     * Run the tool
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function execute(array $params): array
    {
        $runId = (int)($params['run_id'] ?? 0);
        $run = $runId > 0 ? $this->runReader->getRun($runId) : $this->runReader->getLatestRun();
        if ($run === null) {
            return ['error' => $runId > 0 ? 'No benchmark run #' . $runId : 'No benchmark run recorded yet'];
        }
        return ['run_id' => $run->runId, 'markdown' => $this->runExporter->markdown($run)];
    }

    /**
     * @inheritdoc
     */
    protected function fields(): array
    {
        return ['run_id', 'markdown'];
    }
}
