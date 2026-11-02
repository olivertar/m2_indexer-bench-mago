<?php
/**
 * Copyright © Orangecat. All rights reserved.
 */
declare(strict_types=1);

namespace Orangecat\IndexerBenchMago\Service\Tool;

use Orangecat\IndexerBench\Model\History\HistoryBuilder;
use Orangecat\IndexerBench\Model\History\LabelSummary;
use Orangecat\IndexerBench\Model\Run\StoredRun;

/**
 * Recent benchmark runs of one indexer and the median of one metric per label (indexer-bench:history)
 */
class HistoryTool extends AbstractReadTool
{
    public const NAME = 'indexer_bench_history';
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 20;

    /**
     * @param HistoryBuilder $historyBuilder
     */
    public function __construct(
        private readonly HistoryBuilder $historyBuilder
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
        return 'Lists the recent Indexer Bench benchmark runs of one indexer (label, status, a metric such as '
            . 'wall_time_s) and the median and trend per label. Read only; it never starts a run.';
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
                'indexer' => ['type' => 'string', 'description' => 'Indexer ID, e.g. catalog_product_price'],
                'metric' => ['type' => 'string', 'description' => 'Metric code; default wall_time_s'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_LIMIT],
            ],
            'required' => ['indexer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getInstructions(): string
    {
        return 'Values are the chosen metric; every metric is a cost, so lower is better. "trend_pct" compares a '
            . 'label with the previous label of the same kind (full or partial). Runs started from the command '
            . 'line (bin/magento indexer-bench:run) on a copy of the database; you cannot start one.';
    }

    /**
     * Run the tool
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function execute(array $params): array
    {
        $indexerId = (string)($params['indexer'] ?? '');
        if ($indexerId === '') {
            return ['error' => 'indexer is required'];
        }
        $metric = (string)($params['metric'] ?? '') ?: 'wall_time_s';
        $limit = min(self::MAX_LIMIT, max(1, (int)($params['limit'] ?? self::DEFAULT_LIMIT)));
        $history = $this->historyBuilder->build($indexerId, $limit, $metric);

        return [
            'metric' => $metric,
            'runs' => array_map(static fn (StoredRun $run): array => [
                'run_id' => $run->runId,
                'started_at' => $run->startedAt,
                'label' => $run->label,
                'kind' => $run->kind,
                'status' => $run->status,
                'value' => $run->metrics[$metric] ?? null,
            ], $history->runs),
            'labels' => array_map(static fn (LabelSummary $summary): array => [
                'label' => $summary->label,
                'runs' => $summary->runCount,
                'median' => $summary->median,
                'min' => $summary->min,
                'max' => $summary->max,
                'trend_pct' => $summary->deltaPct,
            ], $history->labels),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function fields(): array
    {
        return [
            'metric', 'runs', 'labels', 'run_id', 'started_at', 'label', 'kind', 'status', 'value', 'median', 'min',
            'max', 'trend_pct',
        ];
    }
}
