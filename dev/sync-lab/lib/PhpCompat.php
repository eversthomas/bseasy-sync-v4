<?php
declare(strict_types=1);

/** @param array<mixed> $array */
function bes_sync_lab_is_list(array $array): bool
{
    if ($array === []) {
        return true;
    }
    return array_keys($array) === range(0, count($array) - 1);
}
