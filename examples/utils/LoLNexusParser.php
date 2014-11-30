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

use Buzz\Browser;
use EloGank\Replay\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class LoLNexusParser
{
    const REGION_NA   = 1;
    const REGION_EUW  = 2;
    const REGION_EUNE = 3;
    const REGION_BRA  = 4;
    const REGION_TR   = 5;
    const REGION_RU   = 6;
    const REGION_LA1  = 7;
    const REGION_LA2  = 8;
    const REGION_OC   = 9;

    /**
     * @var string
     */
    protected $region;

    /**
     * @var int
     */
    protected $gameId;

    /**
     * @var string
     */
    protected $encryptionKey;


    /**
     * @param int             $regionId
     * @param OutputInterface $output
     */
    public function parse($regionId = self::REGION_EUW, OutputInterface $output = null)
    {
        $hasOutput = null != $output;
        if ($hasOutput) {
            $output->write("Parsing homepage...\t\t\t");
        }

        $buzz = new Browser();
        $response = $buzz->get('http://www.lolnexus.com/recent-games?filter-region=' . $regionId . '&filter-sort=1');

        if (!preg_match('/<a class="green-button scouter-button" href="\/EUW\/search\?name=(.*)">Live Game<\/a>/', $response->getContent(), $matches)) {
            throw new \RuntimeException('Cannot parse LoL Nexus website, maybe down ?');
        }

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
            $output->write("Parsing game page...\t\t\t");
        }

        $response = $buzz->get('http://www.lolnexus.com/ajax/get-game-info/EUW.json?name=' . urlencode($matches[1]));

        if (!preg_match('/lrf:\/\/spectator [0-9.:]+ (.*) ([0-9]+) ([A-Z0-9]+) [0-9\.]+/', $response->getContent(), $matches)) {
            throw new \RuntimeException('Cannot parse LoL Nexus game page, the game may be ended, please retry.');
        }

        if ($hasOutput) {
            $output->writeln('<info>OK</info>');
        }

        $this->encryptionKey    = $matches[1];
        $this->gameId           = $matches[2];
        $this->region = $matches[3];
    }

    /**
     * @return string
     */
    public function getEncryptionKey()
    {
        return $this->encryptionKey;
    }

    /**
     * @return int
     */
    public function getGameId()
    {
        return $this->gameId;
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }
}
