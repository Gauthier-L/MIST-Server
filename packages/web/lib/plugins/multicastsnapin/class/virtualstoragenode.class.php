<?php
/**
 * VirtualStorageNode class
 *
 * A virtual storage node that redirects downloads to the wrapper endpoint
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class VirtualStorageNode
{
    /**
     * Properties storage
     *
     * @var array
     */
    private $_properties = array();

    /**
     * Constructor
     *
     * @param string $serverIP      Server IP address
     * @param string $wrapperURL    Wrapper endpoint URL
     */
    public function __construct($serverIP, $wrapperURL)
    {
        $this->_properties = array(
            'id' => -1,
            'ip' => $serverIP,
            'path' => '',
            'snapinpath' => '',
            'user' => '',
            'pass' => '',
            'location_url' => $wrapperURL,
        );
    }

    /**
     * Set property
     *
     * @param string $key   Property name
     * @param mixed  $value Property value
     *
     * @return self
     */
    public function set($key, $value)
    {
        $this->_properties[$key] = $value;
        return $this;
    }

    /**
     * Get property
     *
     * @param string $key Property name
     *
     * @return mixed Property value
     */
    public function get($key)
    {
        return isset($this->_properties[$key]) ? $this->_properties[$key] : null;
    }

    /**
     * Check if valid
     *
     * @return bool Always true for virtual node
     */
    public function isValid()
    {
        return true;
    }

    /**
     * Public property for location_url compatibility
     *
     * @var string
     */
    public $location_url;

    /**
     * Magic setter
     *
     * @param string $key   Property name
     * @param mixed  $value Property value
     *
     * @return void
     */
    public function __set($key, $value)
    {
        if ($key === 'location_url') {
            $this->location_url = $value;
        }
        $this->_properties[$key] = $value;
    }

    /**
     * Magic getter
     *
     * @param string $key Property name
     *
     * @return mixed Property value
     */
    public function __get($key)
    {
        if ($key === 'location_url') {
            return $this->location_url;
        }
        return $this->get($key);
    }
}
