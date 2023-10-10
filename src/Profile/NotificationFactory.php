<?php

declare(strict_types=1);

namespace App\Profile;

use InvalidArgumentException;

abstract class NotificationFactory
{
    /**
     * @return AbstractNotification[]
     */
    public static function fromArray(array $list): array
    {
        $output = [];

        foreach ($list as $i => $data) {
            if (isset($data['on']) === false) {
                throw new InvalidArgumentException('Notification ' . $i . ' should have an on key');
            }
            if (count($data) !== 2) {
                throw new InvalidArgumentException('Notification ' . $i . ' should be have only one key besides the on key');
            }

            if (isset($data['email']) === true) {
                $output[] = Notification\EmailNotification::fromArray($data['on'], $data['email']);
                continue;
            }

            $c = $data;
            unset($c['on']);
            reset($c);
            throw new InvalidArgumentException('Unknow key ' . key($c) . ' in notification ' . $i . ' definition ');
        }

        return $output;
    }
}