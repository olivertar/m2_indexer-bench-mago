<?php
/**
 * Copyright © Orangecat. All rights reserved.
 */
declare(strict_types=1);

namespace Orangecat\IndexerBenchMago\Test\Integration;

use MagoAssistant\Mago\Service\Tool\ToolRegistry;
use Magento\TestFramework\Helper\Bootstrap;
use Orangecat\IndexerBenchMago\Service\Tool\CompareTool;
use Orangecat\IndexerBenchMago\Service\Tool\HistoryTool;
use Orangecat\IndexerBenchMago\Service\Tool\ReportTool;
use PHPUnit\Framework\TestCase;

/**
 * The benchmark tools reach Mago through the real DI configuration and read the real run tables
 */
class RegistrationTest extends TestCase
{
    public function testToolsAreRegisteredAndReadTheRunTables(): void
    {
        $tools = Bootstrap::getObjectManager()->get(ToolRegistry::class);

        foreach ([HistoryTool::NAME => HistoryTool::class, CompareTool::NAME => CompareTool::class,
                     ReportTool::NAME => ReportTool::class] as $name => $class) {
            $this->assertInstanceOf($class, $tools->getToolByName($name), $name);
        }
        $history = $tools->getToolByName(HistoryTool::NAME)?->execute(['indexer' => 'catalog_product_price']) ?? [];
        $this->assertSame('wall_time_s', $history['metric'] ?? null);
        $this->assertIsArray($history['runs'] ?? null);
    }
}
