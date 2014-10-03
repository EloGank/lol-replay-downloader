<?php

/*
 * This file is part of the "EloGank League of Legends Commands" package.
 *
 * https://github.com/EloGank/lol-commands-replay
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Util\Replay\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayDownloadCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('elogank:replay:download')
            ->setDescription('Download a live replay')
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        
    }
}