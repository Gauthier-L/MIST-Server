<?php
/**
 * MulticastSnapinSession class
 *
 * Represents a multicast session for snapin deployment
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinSession class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinSession extends FOGController
{
    /**
     * The database table name
     *
     * @var string
     */
    protected $databaseTable = 'multicastSnapinSessions';

    /**
     * The database field mappings
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'mssID',
        'name' => 'mssName',
        'port' => 'mssBasePort',
        'snapinID' => 'mssSnapinID',
        'clients' => 'mssClients',
        'sessclients' => 'mssSessClients',
        'interface' => 'mssInterface',
        'stateID' => 'mssState',
        'starttime' => 'mssStartDateTime',
        'completetime' => 'mssCompleteDateTime',
        'storagegroupID' => 'mssStorageGroupID',
        'percent' => 'mssPercent',
    );

    /**
     * Required database fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'name',
        'snapinID',
        'clients',
    );

    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'snapin',
        'storagegroup',
        'storagenode',
        'hosts',
    );

    /**
     * Database field to class relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Snapin' => array(
            'id',
            'snapinID',
            'snapin',
        ),
        'StorageGroup' => array(
            'id',
            'storagegroupID',
            'storagegroup',
        ),
    );

    /**
     * Get the snapin object
     *
     * @return object The snapin object
     */
    public function getSnapin()
    {
        return $this->get('snapin');
    }

    /**
     * Get the storage group
     *
     * @return object The storage group object
     */
    public function getStorageGroup()
    {
        return $this->get('storagegroup');
    }

    /**
     * Get the storage node (master node of the storage group)
     *
     * @return object|bool The storage node or false
     */
    public function getStorageNode()
    {
        $StorageGroup = $this->getStorageGroup();
        if (!$StorageGroup || !$StorageGroup->isValid()) {
            return false;
        }

        return $StorageGroup->getMasterStorageNode();
    }

    /**
     * Get associated hosts
     *
     * @return array Array of host objects
     */
    public function getHosts()
    {
        return self::getSubObjectIDs(
            'MulticastSnapinSessionAssociation',
            array('msID' => $this->get('id')),
            'hostID'
        );
    }

    /**
     * Add a host to this session
     *
     * @param mixed $host Host ID or Host object
     *
     * @return object This object for chaining
     */
    public function addHost($host)
    {
        if ($host instanceof Host) {
            $host = $host->get('id');
        }

        self::getClass('MulticastSnapinSessionAssociation')
            ->set('msID', $this->get('id'))
            ->set('hostID', $host)
            ->save();

        return $this;
    }

    /**
     * Remove a host from this session
     *
     * @param mixed $host Host ID or Host object
     *
     * @return object This object for chaining
     */
    public function removeHost($host)
    {
        if ($host instanceof Host) {
            $host = $host->get('id');
        }

        self::getClass('MulticastSnapinSessionAssociationManager')
            ->destroy(
                array(
                    'msID' => $this->get('id'),
                    'hostID' => $host,
                )
            );

        return $this;
    }

    /**
     * Check if the session is queued
     *
     * @return bool True if queued
     */
    public function isQueued()
    {
        return $this->get('stateID') == 0;
    }

    /**
     * Check if the session is in progress
     *
     * @return bool True if in progress
     */
    public function isInProgress()
    {
        return $this->get('stateID') == 1;
    }

    /**
     * Check if the session is complete
     *
     * @return bool True if complete
     */
    public function isComplete()
    {
        return $this->get('stateID') == 2;
    }

    /**
     * Check if the session is cancelled
     *
     * @return bool True if cancelled
     */
    public function isCancelled()
    {
        return $this->get('stateID') == 3;
    }

    /**
     * Get state name
     *
     * @return string State name
     */
    public function getStateName()
    {
        $states = array(
            0 => _('Queued'),
            1 => _('In Progress'),
            2 => _('Complete'),
            3 => _('Cancelled'),
        );

        return isset($states[$this->get('stateID')])
            ? $states[$this->get('stateID')]
            : _('Unknown');
    }

    /**
     * Cancel this session
     *
     * @return object This object for chaining
     */
    public function cancel()
    {
        return $this->set('stateID', 3)
            ->set('completetime', self::formatTime('now', 'Y-m-d H:i:s'))
            ->save();
    }

    /**
     * Mark session as in progress
     *
     * @return object This object for chaining
     */
    public function markInProgress()
    {
        return $this->set('stateID', 1)->save();
    }

    /**
     * Mark session as complete
     *
     * @return object This object for chaining
     */
    public function markComplete()
    {
        return $this->set('stateID', 2)
            ->set('completetime', self::formatTime('now', 'Y-m-d H:i:s'))
            ->set('percent', 100)
            ->save();
    }
}
