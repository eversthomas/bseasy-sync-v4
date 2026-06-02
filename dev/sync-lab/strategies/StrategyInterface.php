<?php
declare(strict_types=1);

interface BesSyncLab_StrategyInterface
{
    public function name(): string;

    /**
     * @param list<int> $memberIds
     * @return array{members:list<array<string,mixed>>,metrics:BesSyncLab_Metrics}
     */
    public function run(array $memberIds): array;
}
