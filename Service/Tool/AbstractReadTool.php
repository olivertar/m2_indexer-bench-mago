<?php
/**
 * Copyright © Orangecat. All rights reserved.
 */
declare(strict_types=1);

namespace Orangecat\IndexerBenchMago\Service\Tool;

use MagoAssistant\Mago\Api\Tool\ToolInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

/**
 * Read-only benchmark tool: one ACL resource, and every returned field is benchmark data, not personal data
 *
 * Starting a benchmark run is not offered: it takes minutes to hours, must never run in production, and a tool
 * runs inside the chat request without a timeout.
 */
abstract class AbstractReadTool implements ToolInterface
{
    public const ACL = 'Orangecat_IndexerBenchMago::view';

    /**
     * Keys of the result, at any depth
     *
     * @return string[]
     */
    abstract protected function fields(): array;

    /**
     * ACL resource the admin needs
     *
     * @param array<string,mixed> $input
     * @return string
     */
    public function getMagentoAcl(array $input = []): string
    {
        return self::ACL;
    }

    /**
     * @inheritdoc
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Whether this call only reads
     *
     * @param array<string,mixed> $input
     * @return bool
     */
    public function isReadOnlyAction(array $input): bool
    {
        return true;
    }

    /**
     * Privacy rule of every returned field
     *
     * @param string $action
     * @return array<string,array<int,string>>
     */
    public function getFieldClassification(string $action = ''): array
    {
        return array_fill_keys($this->fields(), [PiiClass::PUBLIC]);
    }
}
