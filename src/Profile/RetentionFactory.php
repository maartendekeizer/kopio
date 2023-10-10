<?php

declare(strict_types=1);

namespace App\Profile;

use InvalidArgumentException;

abstract class RetentionFactory
{
    public static function fromArray(array $data): AbstractRetention
    {
        if (count($data) !== 1) {
            throw new InvalidArgumentException('Retention should be have one key');
        }

        if (isset($data['simple']) === true) {
            return Retention\SimpleRetention::fromArray($data['simple']);
        }

        reset($data);
        throw new InvalidArgumentException('Unknow key in target definition ' . key($data));
    }
}