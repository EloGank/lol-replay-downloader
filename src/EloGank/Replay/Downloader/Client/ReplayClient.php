<?php

/*
 * This file is part of the "EloGank League of Legends Replay Downloader" package.
 *
 * https://github.com/EloGank/lol-replay-downloader
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EloGank\Replay\Downloader\Client;

use Buzz\Browser;
use Buzz\Exception\RequestException;
use EloGank\Replay\Downloader\Client\Exception\TimeoutException;
use EloGank\Replay\Downloader\Client\Exception\UnknownRegionException;

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
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(static::getDefaultConfigs(), $options);

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
        $metas = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_META, $region, $gameId));

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

        return true;
    }

    /**
     * @param string $region
     * @param int    $gameId
     * @param int    $chunkId
     *
     * @return string|bool
     */
    public function getLastChunkInfo($region, $gameId, $chunkId)
    {
        try {
            $chunkInfo = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_CHUNK_INFO, $region, $gameId, $chunkId));
        } catch (TimeoutException $e) {
            return false;
        } catch (RequestException $e) {
            return false;
        }

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
        } catch (TimeoutException $e) {
            return false;
        } catch (RequestException $e) {
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
        } catch (TimeoutException $e) {
            return false;
        } catch (RequestException $e) {
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
     * @return string
     */
    public function downloadEndStats($region, $gameId)
    {
        try {
            $endStats = $this->buzz->get(sprintf($this->getUrl($region) . self::URL_ENDSTATS, $region, $gameId));
        } catch (TimeoutException $e) {
            return false;
        } catch (RequestException $e) {
            return false;
        }

        // base64_decode to decode endStats file

        return $endStats->getContent();
    }

    /**
     * @return string
     */
    public function getObserverVersion()
    {
        // version seems to be the same for all servers
        try {
            $version = $this->buzz->get($this->getUrl('EUW1') . self::URL_VERSION);
        } catch (TimeoutException $e) {
            return false;
        } catch (RequestException $e) {
            return false;
        }

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
    public static function getDefaultConfigs()
    {
        return [
            'buzz.class'   => '\Buzz\Browser',
            'buzz.timeout' => 10,
            'replay.http_client.servers' => [
                'EUW1' => '185.40.64.163:80',
                'NA1'  => '192.64.174.163:80',
                'EUN1' => '110.45.191.11:8088',
                'KR'   => '110.45.191.11:8088',
                'BR1'  => '66.151.33.19:80',
                'TR1'  => '95.172.65.242:80',
                'TW'   => '112.121.84.194:8088',
                'LA1'  => '66.151.33.19:80',
                'LA2'  => '66.151.33.19:80',
                'RU'   => '95.172.65.242:80',
                'PBE1' => '69.88.138.29:8088',
                'OC1'  => '95.172.65.242:80'
            ]
        ];
    }
}