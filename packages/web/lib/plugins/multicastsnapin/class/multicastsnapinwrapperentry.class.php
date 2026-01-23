<?php
/**
 * MulticastSnapinWrapperEntry class
 *
 * Represents a generated wrapper script for a host in a multicast session
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinWrapperEntry class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinWrapperEntry extends FOGController
{
    /**
     * The database table name
     *
     * @var string
     */
    protected $databaseTable = 'multicastSnapinWrappers';

    /**
     * The database field mappings
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'mswID',
        'sessionID' => 'mswSessionID',
        'hostID' => 'mswHostID',
        'snapinjobID' => 'mswSnapinJobID',
        'snapintaskID' => 'mswSnapinTaskID',
        'script' => 'mswScript',
        'hash' => 'mswHash',
        'ostype' => 'mswOSType',
        'createdtime' => 'mswCreatedTime',
    );

    /**
     * The required database fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'sessionID',
        'hostID',
        'script',
        'hash',
    );

    /**
     * Additional database fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'session',
        'host',
        'snapinjob',
        'snapintask',
    );

    /**
     * Database field to class relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'MulticastSnapinSession' => array(
            'id',
            'sessionID',
            'session'
        ),
        'Host' => array(
            'id',
            'hostID',
            'host'
        ),
        'SnapinJob' => array(
            'id',
            'snapinjobID',
            'snapinjob'
        ),
        'SnapinTask' => array(
            'id',
            'snapintaskID',
            'snapintask'
        ),
    );

    /**
     * Get the session object
     *
     * @return object The session
     */
    public function getSession()
    {
        return $this->get('session');
    }

    /**
     * Get the host object
     *
     * @return object The host
     */
    public function getHost()
    {
        return $this->get('host');
    }

    /**
     * Get the snapin job object
     *
     * @return object The snapin job
     */
    public function getSnapinJob()
    {
        return $this->get('snapinjob');
    }

    /**
     * Get the snapin task object
     *
     * @return object The snapin task
     */
    public function getSnapinTask()
    {
        return $this->get('snapintask');
    }
}
