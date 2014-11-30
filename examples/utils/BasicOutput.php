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
     * Writes a message to the output.
     *
     * @param string|array $messages The message as an array of lines or a single string
     * @param bool         $newline  Whether to add a newline
     * @param int          $type     The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
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
     * Writes a message to the output and adds a newline at the end.
     *
     * @param string|array $messages The message as an array of lines of a single string
     * @param int          $type     The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
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

} 