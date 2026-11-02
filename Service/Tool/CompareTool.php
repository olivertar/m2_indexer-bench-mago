<?php
/**
 * Copyright © Orangecat
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace Orangecat\IndexerBenchMago\Service\Tool;

use Orangecat\IndexerBench\Model\Compare\MetricComparator;
use Orangecat\IndexerBench\Model\Compare\MetricComparison;
use Orangecat\IndexerBench\Model\Compare\RunSelector;
use Orangecat\IndexerBench\Model\Fingerprint\FingerprintComparator;
use Orangecat\IndexerBench\Model\Snapshot\SnapshotComparer;

/**
 * Candidate against baseline: verdict per metric and whether the index output matches (indexer-bench:compare)
 */
class CompareTool extends AbstractReadTool
{
    public const NAME = 'indexer_bench_compare';
    private const DEFAULT_THRESHOLD = 10.0;

    /**
     * @param RunSelector $selector
     * @param MetricComparator $metricComparator
     * @param FingerprintComparator $fingerprintComparator
     * @param SnapshotComparer $snapshotComparer
     */
    public function __construct(
        private readonly RunSelector $selector,
        private readonly MetricComparator $metricComparator,
        private readonly FingerprintComparator $fingerprintComparator,
        private readonly SnapshotComparer $snapshotComparer
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
        return 'Compares two Indexer Bench benchmark results of one indexer (a run ID or a label, median of its '
            . 'runs): change and verdict per metric, and whether the index output is identical. Read only.';
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
                'baseline' => ['type' => 'string', 'description' => 'Run ID or label, e.g. core'],
                'candidate' => ['type' => 'string', 'description' => 'Run ID or label, e.g. boost'],
                'threshold' => ['type' => 'number', 'minimum' => 0, 'description' => 'Change in %; default 10'],
            ],
            'required' => ['indexer', 'baseline', 'candidate'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getInstructions(): string
    {
        return 'Every metric is a cost: a negative delta_pct is better. Lead with wall_time_s and the output '
            . 'verdict: "MATCH" means both index outputs are identical, "DIFFER" that the candidate changes the '
            . 'index (never call it an improvement then). Mention fingerprint differences: the comparison may not '
            . 'hold when the environments differ.';
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
        try {
            $baseline = $this->selector->select($indexerId, (string)($params['baseline'] ?? ''));
            $candidate = $this->selector->select($indexerId, (string)($params['candidate'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }
        $threshold = max(0.0, (float)($params['threshold'] ?? self::DEFAULT_THRESHOLD));
        $comparisons = $this->metricComparator->compare($baseline->metrics, $candidate->metrics, $threshold);
        try {
            $snapshot = $this->snapshotComparer->compare($indexerId, $baseline->label, $candidate->label);
            $output = $snapshot->matches() ? 'MATCH' : 'DIFFER';
            $rows = $snapshot->candidateRows();
        } catch (\InvalidArgumentException $e) {
            $output = 'not compared: ' . $e->getMessage();
            $rows = null;
        }

        return [
            'baseline' => $baseline->describe(),
            'candidate' => $candidate->describe(),
            'fingerprint_differences' => $this->fingerprintComparator->differences(
                $baseline->fingerprint ?? [],
                $candidate->fingerprint ?? []
            ),
            'metrics' => array_map(static fn (MetricComparison $comparison): array => [
                'code' => $comparison->code,
                'baseline' => $comparison->baseline,
                'candidate' => $comparison->candidate,
                'delta_pct' => $comparison->deltaPct,
                'verdict' => $comparison->verdict,
            ], $comparisons),
            'output' => $output,
            'output_rows' => $rows,
            'regressions' => array_values(array_map(
                static fn (MetricComparison $comparison): string => $comparison->code,
                array_filter(
                    $comparisons,
                    static fn (MetricComparison $comparison): bool =>
                        $comparison->verdict === MetricComparison::REGRESSION
                )
            )),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function fields(): array
    {
        return [
            'baseline', 'candidate', 'fingerprint_differences', 'metrics', 'code', 'delta_pct', 'verdict', 'output',
            'output_rows', 'regressions',
        ];
    }
}
