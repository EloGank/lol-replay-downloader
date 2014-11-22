<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class Replay implements ReplayInterface
{
    /**
     * @var array
     */
    protected $metas;

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
     * @var int
     */
    protected $duration;

    /**
     * @var int
     */
    private $lastChunkId;

    /**
     * @var int
     */
    private $lastKeyFrameId;

    /**
     * @var int
     */
    private $downloadRetry;


    /**
     * @param string $region
     * @param int    $gameId
     * @param string $encryptionKey
     */
    public function __construct($region, $gameId, $encryptionKey)
    {
        $this->region        = $region;
        $this->gameId        = $gameId;
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * @return array
     */
    public function getMetas()
    {
        return $this->metas;
    }

    /**
     * @param array $metas
     */
    public function setMetas(array $metas)
    {
        $this->metas = $metas;
    }

    /**
     * @return string
     */
    public function getEncryptionKey()
    {
        return $this->encryptionKey;
    }

    /**
     * @param string $encryptionKey
     */
    public function setEncryptionKey($encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * @return int
     */
    public function getGameId()
    {
        return $this->gameId;
    }

    /**
     * @param int $gameId
     */
    public function setGameId($gameId)
    {
        $this->gameId = $gameId;
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * @param string $region
     */
    public function setRegion($region)
    {
        $this->region = $region;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    /**
     * @return int
     */
    public function getLastChunkId()
    {
        return $this->lastChunkId;
    }

    /**
     * @param int $lastChunkId
     */
    public function setLastChunkId($lastChunkId)
    {
        $this->lastChunkId = $lastChunkId;
    }

    /**
     * @return int
     */
    public function getLastKeyFrameId()
    {
        return $this->lastKeyFrameId;
    }

    /**
     * @param int $lastKeyFrameId
     */
    public function setLastKeyFrameId($lastKeyFrameId)
    {
        $this->lastKeyFrameId = $lastKeyFrameId;
    }

    /**
     * @return int
     */
    public function getDownloadRetry()
    {
        return $this->downloadRetry;
    }

    /**
     *
     */
    public function addDownloadRetry()
    {
        ++$this->downloadRetry;
    }

    /**
     *
     */
    public function resetDownloadRetry()
    {
        $this->downloadRetry = 0;
    }
}