<?php

/*
  MIT License
  Copyright (c) 2010 - 2014 Daniel Hoffend, Peter Petermann

  Permission is hereby granted, free of charge, to any person
  obtaining a copy of this software and associated documentation
  files (the "Software"), to deal in the Software without
  restriction, including without limitation the rights to use,
  copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the
  Software is furnished to do so, subject to the following
  conditions:

  The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
  OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
  NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
  WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
  FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
  OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Pheal\Cache;

class AdaptableStorage implements CanCache
{

    /**
     *
     * @var Adaptable
     */
    protected $adapter;

    /**
     *
     * @var array
     */
    protected $options = array(
        'prefix' => 'Pheal'
    );

    /**
     * Load XML from cache
     *
     * @param int $userid
     * @param string $apikey
     * @param string $scope
     * @param string $name
     * @param array $args
     * @return false|string
     */
    public function load($userid, $apikey, $scope, $name, $args)
    {
        $key = $this->getKey($userid, $apikey, $scope, $name, $args);
        if (!$xml = $this->adapter->load($key)) {
            return false;
        }

        if ($this->validateCache($xml)) {
            return $xml;
        }

        return false;
    }

    /**
     * Save XML from cache
     *
     * @param int $userid
     * @param string $apikey
     * @param string $scope
     * @param string $name
     * @param array $args
     * @param string $xml
     * @return boolean
     */
    public function save($userid, $apikey, $scope, $name, $args, $xml)
    {
        $key = $this->getKey($userid, $apikey, $scope, $name, $args);
        $timeout = time() + $this->getTimeout($xml);

        return $this->adapter->save($key, $xml, $timeout);
    }

    /**
     * Create an adapter key (prepend `prefix` to not conflict with other keys)
     *
     * @param int $userid
     * @param string $apikey
     * @param string $scope
     * @param string $name
     * @param array $args
     * @return string
     */
    protected function getKey($userid, $apikey, $scope, $name, $args)
    {
        $key = implode('|', compact('userid', 'apikey', 'scope', 'name'));

        foreach ($args as $k => $v) {
            if (!in_array(strtolower($key), array('userid', 'apikey', 'keyid', 'vcode'))) {
                $key .= sprintf('|%s:%s', $k, $v);
            }
        }

        return sprintf('%s|%s', $this->options['prefix'], $key);
    }

    /**
     *  Return the number of seconds the XML is valid. Will never be less than 1.
     *
     * @param string $xml
     * @return int
     */
    protected function getTimeout($xml)
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $xml = @new \SimpleXMLElement($xml);
        $dt = (int) strtotime($xml->cachedUntil);
        $time = time();

        date_default_timezone_set($tz);

        return max(1, $dt - $time);
    }

    /**
     * Validate the cached xml if it is still valid. This contains a name hack
     * to work arround EVE API giving wrong cachedUntil values
     *
     * @param string $xml
     * @return boolean
     */
    public function validateCache($xml)
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set("UTC");

        $xml = @new \SimpleXMLElement($xml);
        $dt = (int) strtotime($xml->cachedUntil);
        $time = time();

        date_default_timezone_set($tz);

        return (bool) ($dt > $time);
    }

    /**
     * Initialise adaptable storage cache.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = $options + $this->options;
        $this->adapter = new Adaptable(array(
            'save' => $this->options['save'], 'load' => $this->options['load']
        ));
    }
}
