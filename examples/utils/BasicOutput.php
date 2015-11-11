<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Example\Utils;

use EloGank\Replay\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class BasicOutput implements OutputInterface
{
    /**
     * @inheritdoc
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_TYPE_NORMAL)
    {
        if (is_array($messages)) {
            foreach ($messages as $message) {
                echo $message;
            }
        } else {
            echo $messages;
        }
    }

    /**
     * @inheritdoc
     */
    public function writeln($messages, $type = self::OUTPUT_TYPE_NORMAL)
    {
        if (is_array($messages)) {
            foreach ($messages as $message) {
                echo $message . PHP_EOL;
            }
        } else {
            echo $messages . PHP_EOL;
        }
    }

    /**
     * @inheritdoc
     */
    public function getVerbosity()
    {
        // Nothing
    }

    /**
     * @inheritdoc
     */
    public function setVerbosity($level)
    {
        // Nothing
    }
}