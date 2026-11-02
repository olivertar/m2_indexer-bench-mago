<?php
/**
 * Copyright © Orangecat
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace Orangecat\IndexerBenchMago\Test\Unit\Service\Tool;

use MagoAssistant\Mago\Service\Privacy\PiiClass;
use Orangecat\IndexerBench\Model\Compare\MetricComparator;
use Orangecat\IndexerBench\Model\Compare\MetricComparison;
use Orangecat\IndexerBench\Model\Compare\RunSelection;
use Orangecat\IndexerBench\Model\Compare\RunSelector;
use Orangecat\IndexerBench\Model\Export\RunExporter;
use Orangecat\IndexerBench\Model\Fingerprint\FingerprintComparator;
use Orangecat\IndexerBench\Model\History\History;
use Orangecat\IndexerBench\Model\History\HistoryBuilder;
use Orangecat\IndexerBench\Model\History\LabelSummary;
use Orangecat\IndexerBench\Model\Run\RunReader;
use Orangecat\IndexerBench\Model\Run\StoredRun;
use Orangecat\IndexerBench\Model\Snapshot\SnapshotComparer;
use Orangecat\IndexerBench\Model\Snapshot\SnapshotComparison;
use Orangecat\IndexerBenchMago\Service\Tool\CompareTool;
use Orangecat\IndexerBenchMago\Service\Tool\HistoryTool;
use Orangecat\IndexerBenchMago\Service\Tool\ReportTool;
use PHPUnit\Framework\TestCase;

class BenchToolsTest extends TestCase
{
    public function testHistoryListsRunsAndLabelsWithTheMetric(): void
    {
        $builder = $this->createMock(HistoryBuilder::class);
        $builder->expects($this->once())->method('build')->with('catalog_product_price', 10, 'wall_time_s')
            ->willReturn(new History(
                [$this->storedRun(12, 'core', 41.7)],
                [new LabelSummary('core', 3, 41.7, 40.6, 42.5, null)]
            ));
        $tool = new HistoryTool($builder);

        $result = $tool->execute(['indexer' => 'catalog_product_price']);

        $this->assertSame([
            'metric' => 'wall_time_s',
            'runs' => [[
                'run_id' => 12,
                'started_at' => '2026-11-02 10:00:00',
                'label' => 'core',
                'kind' => 'full',
                'status' => 'ok',
                'value' => 41.7,
            ]],
            'labels' => [[
                'label' => 'core',
                'runs' => 3,
                'median' => 41.7,
                'min' => 40.6,
                'max' => 42.5,
                'trend_pct' => null,
            ]],
        ], $result);
        $this->assertPublicAndReadOnly($tool, $result);
    }

    public function testCompareGivesVerdictsOutputAndRegressions(): void
    {
        $selector = $this->createStub(RunSelector::class);
        $selector->method('select')->willReturnCallback(
            static fn (string $indexerId, string $reference): RunSelection => $reference === 'core'
                ? new RunSelection('core', [1, 2, 3], true, ['wall_time_s' => 41.7], [], 'full')
                : throw new \InvalidArgumentException('No comparable run labelled "' . $reference . '"')
        );
        $metrics = $this->createStub(MetricComparator::class);
        $metrics->method('compare')->willReturn([
            new MetricComparison('wall_time_s', 41.7, 35.9, -13.9, MetricComparison::IMPROVED),
        ]);
        $fingerprints = $this->createStub(FingerprintComparator::class);
        $fingerprints->method('differences')->willReturn([]);
        $snapshots = $this->createStub(SnapshotComparer::class);
        $snapshots->method('compare')->willReturn(new SnapshotComparison('core', 'core', true, 488163, []));
        $tool = new CompareTool($selector, $metrics, $fingerprints, $snapshots);

        $result = $tool->execute(['indexer' => 'catalog_product_price', 'baseline' => 'core', 'candidate' => 'core']);
        $error = $tool->execute(['indexer' => 'catalog_product_price', 'baseline' => 'core', 'candidate' => 'x']);

        $this->assertSame([[
            'code' => 'wall_time_s',
            'baseline' => 41.7,
            'candidate' => 35.9,
            'delta_pct' => -13.9,
            'verdict' => 'improved',
        ]], $result['metrics']);
        $this->assertSame('MATCH', $result['output']);
        $this->assertSame(488163, $result['output_rows']);
        $this->assertSame([], $result['regressions']);
        $this->assertSame(['error' => 'No comparable run labelled "x"'], $error);
        $this->assertPublicAndReadOnly($tool, $result);
    }

    public function testReportIsTheMarkdownExportOfARunOrOfTheLatestOne(): void
    {
        $reader = $this->createStub(RunReader::class);
        $reader->method('getRun')->willReturnCallback(
            fn (int $id): ?StoredRun => $id === 12 ? $this->storedRun(12) : null
        );
        $reader->method('getLatestRun')->willReturn($this->storedRun(13));
        $exporter = $this->createStub(RunExporter::class);
        $exporter->method('markdown')->willReturnCallback(
            static fn (StoredRun $run): string => '## run ' . $run->runId
        );
        $tool = new ReportTool($reader, $exporter);

        $this->assertSame(['run_id' => 12, 'markdown' => '## run 12'], $tool->execute(['run_id' => 12]));
        $this->assertSame(['run_id' => 13, 'markdown' => '## run 13'], $tool->execute([]));
        $this->assertSame(['error' => 'No benchmark run #99'], $tool->execute(['run_id' => 99]));
        $this->assertPublicAndReadOnly($tool, $tool->execute([]));
    }

    /**
     * Every returned key is public and the tool only reads, behind the benchmark ACL
     *
     * @param HistoryTool|CompareTool|ReportTool $tool
     * @param array<string,mixed> $result
     * @return void
     */
    private function assertPublicAndReadOnly(HistoryTool|CompareTool|ReportTool $tool, array $result): void
    {
        $rows = array_filter($result, static fn ($value): bool => is_array($value) && is_array($value[0] ?? null));
        $keys = array_merge(
            array_keys($result),
            ...array_values(array_map(static fn (array $value): array => array_keys($value[0]), $rows))
        );
        foreach ($keys as $key) {
            $this->assertSame([PiiClass::PUBLIC], $tool->getFieldClassification()[$key] ?? null, (string)$key);
        }
        $this->assertTrue($tool->isReadOnly());
        $this->assertSame('Orangecat_IndexerBenchMago::view', $tool->getMagentoAcl());
    }

    /**
     * Stored successful full run
     *
     * @param int $runId
     * @param string $label
     * @param float $wallTime
     * @return StoredRun
     */
    private function storedRun(int $runId, string $label = 'core', float $wallTime = 1.0): StoredRun
    {
        return new StoredRun(
            $runId,
            'catalog_product_price',
            $label,
            'baseline',
            'full',
            0,
            false,
            false,
            'ok',
            '2026-11-02 10:00:00',
            [],
            ['wall_time_s' => $wallTime],
            'private notes'
        );
    }
}
