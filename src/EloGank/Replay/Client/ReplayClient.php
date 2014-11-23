<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Client;

use Buzz\Browser;
use EloGank\Replay\Client\Exception\TimeoutException;
use EloGank\Replay\Client\Exception\UnknownRegionException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class ReplayClient
{
    const URL_PREFIX            = '/observer-mode/rest/consumer';

    const URL_VERSION           = '/version';
    const URL_META              = '/getGameMetaData/%s/%d/0/token';
    const URL_CHUNK_INFO        = '/getLastChunkInfo/%s/%d/%d/token';
    const URL_CHUNK_DOWNLOAD    = '/getGameDataChunk/%s/%d/%d/token';
    const URL_KEYFRAME_DOWNLOAD = '/getKeyFrame/%s/%d/%d/token';
    const URL_ENDSTATS          = '/endOfGameStats/%s/%d/null';

    /**
     * @var array
     */
    protected $options;

    /**
     * @var Browser
     */
    private $buzz;

    /**
     * @var array
     */
    private $servers;

    /**
     * @var array
     */
    private $metasCache;


    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->getDefaultOptions(), $options);

        $buzzClassName = $this->options['buzz.class'];
        $this->buzz    = new $buzzClassName();
        $this->servers = $this->options['replay.http_client.servers'];

        // Increase timeout
        $this->buzz->getClient()->setTimeout($this->options['buzz.timeout']);
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @return string
     */
    public function getMetas($region, $gameId)
    {
        if (isset($this->metasCache) && null != $this->metasCache) {
            $metas = $this->metasCache;
        }
        else {
            $metas = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_META, $region, $gameId));
        }

        return $metas->getContent();
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @return bool
     */
    public function isGameExists($region, $gameId)
    {
        $metas = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_META, $region, $gameId));

        // Check if game exists
        if ('HTTP/1.1 500 Internal Server Error' == $metas->getHeaders()[0]) {
            return false;
        }

        $this->metasCache = $metas;

        return true;
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param int    $chunkId
     *
     * @return string
     */
    public function getLastChunkInfo($region, $gameId, $chunkId)
    {
        $chunkInfo = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_CHUNK_INFO, $region, $gameId, $chunkId));

        return $chunkInfo->getContent();
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param int    $chunkId
     *
     * @return bool|string
     */
    public function downloadChunk($region, $gameId, $chunkId)
    {
        try {
            $chunk = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_CHUNK_DOWNLOAD, $region, $gameId, $chunkId));
        }
        catch (TimeoutException $e) {
            return false;
        }

        // Check if game exists
        $header = $chunk->getHeaders()[0];
        if ('HTTP/1.1 500 Internal Server Error' == $header || 'HTTP/1.1 404 Not Found' == $header) {
            return false;
        }

        return $chunk->getContent();
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param int    $keyframeId
     *
     * @return bool|string
     */
    public function downloadKeyframe($region, $gameId, $keyframeId)
    {
        try {
            $keyframe = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_KEYFRAME_DOWNLOAD, $region, $gameId, $keyframeId));
        }
        catch (TimeoutException $e) {
            return false;
        }

        // Check if game exists
        $header = $keyframe->getHeaders()[0];
        if ('HTTP/1.1 500 Internal Server Error' == $header || 'HTTP/1.1 404 Not Found' == $header) {
            return false;
        }

        return $keyframe->getContent();
    }

    /**
     * @param string $region
     * @param int    $gameId
     *
     * @return mixed
     *
     * @deprecated
     */
    public function downloadEndStats($region, $gameId)
    {
        try {
            $endStats = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_ENDSTATS, $region, $gameId));
        }
        catch (TimeoutException $e) {
            return false;
        }

        return $endStats->getContent();
    }

    /**
     * @return string
     */
    public function getObserverVersion()
    {
        $version = $this->buzz->get($this->getUrl('EUW1') . self::URL_VERSION);

        return $version->getContent();
    }

    /**
     * @param string $region
     *
     * @return string
     *
     * @throws UnknownRegionException
     */
    private function getUrl($region)
    {
        if (!isset($this->servers[$region])) {
            $availableRegions = '';
            foreach ($this->servers as $key => $item) {
                $availableRegions .= $key . ', ';
            }

            $availableRegions = substr($availableRegions, 0, -2);

            throw new UnknownRegionException('The region "' . $region . '" is unknown. Available regions : ' . $availableRegions);
        }

        return $this->servers[$region] . self::URL_PREFIX;
    }

    /**
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'buzz.class'   => '\Browser\Buzz',
            'buzz.timeout' => 10,
            'replay.http_client.servers' => [
                'EUW1' => '185.40.64.163:80',
                'NA1'  => '216.133.234.17:8088',
                'KR'   => '110.45.191.11:8088',
                'BR1'  => '66.151.33.19:80',
                'TR1'  => '95.172.65.242:80',
                'TW'   => '112.121.84.194:8088',
                'LA1'  => '66.150.148.234:80',
                'RU'   => '95.172.65.242:80',
                'PBE1' => '69.88.138.29:8088'
            ]
        ];
    }
}