<?php
/**
 * MulticastSnapinTask class
 *
 * Represents a running multicast snapin task
 * Based on MulticastTask pattern
 *
 * @category Service
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinTask class
 *
 * @category Service
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinTask extends FOGService
{
    /**
     * The multicast session
     *
     * @var object
     */
    private $_MultiSess;

    /**
     * Internal ID
     *
     * @var int
     */
    private $_intID;

    /**
     * Snapin path
     *
     * @var string
     */
    private $_snapinPath;

    /**
     * Storage node ID
     *
     * @var int
     */
    private $_storageNodeID;

    /**
     * Process reference
     *
     * @var resource
     */
    public $procRef;

    /**
     * Get all multicast snapin tasks
     *
     * @param string $snapinPath     Snapin base path
     * @param int    $storageNodeID  Storage node ID
     * @param array  $queuedStates   States to look for
     *
     * @return array Array of MulticastSnapinTask objects
     */
    public function getAllMulticastSnapinTasks(
        $snapinPath,
        $storageNodeID,
        $queuedStates
    ) {
        // Get all sessions in queued/progress states for this storage group
        $StorageNode = self::getClass('StorageNode', $storageNodeID);
        if (!$StorageNode->isValid()) {
            return [];
        }

        $StorageGroup = $StorageNode->getStorageGroup();
        if (!$StorageGroup->isValid()) {
            return [];
        }

        $sessions = self::getClass('MulticastSnapinSessionManager')->find(
            [
                'stateID' => $queuedStates,
                'storagegroupID' => $StorageGroup->get('id'),
            ]
        );

        $tasks = [];
        foreach ((array) $sessions as &$session) {
            if (!$session->isValid()) {
                continue;
            }

            $task = new self();
            $task->_MultiSess = $session;
            $task->_intID = $session->get('id');
            $task->_snapinPath = $snapinPath;
            $task->_storageNodeID = $storageNodeID;

            $tasks[] = $task;

            unset($session);
        }

        return $tasks;
    }

    /**
     * Get the session object
     *
     * @return object The session
     */
    public function getSess()
    {
        return $this->_MultiSess;
    }

    /**
     * Get the task ID
     *
     * @return int The ID
     */
    public function getID()
    {
        return $this->_intID;
    }

    /**
     * Get the snapin file path
     *
     * @return string The snapin path
     */
    public function getSnapinPath()
    {
        $snapin = $this->_MultiSess->getSnapin();
        if (!$snapin || !$snapin->isValid()) {
            return false;
        }

        return sprintf(
            '%s/%s',
            rtrim($this->_snapinPath, '/'),
            $snapin->get('file')
        );
    }

    /**
     * Get the port base
     *
     * @return int The port
     */
    public function getPortBase()
    {
        return $this->_MultiSess->get('port');
    }

    /**
     * Get the client count
     *
     * @return int Number of clients
     */
    public function getClientCount()
    {
        return $this->_MultiSess->get('clients');
    }

    /**
     * Get the network interface
     *
     * @return string The interface
     */
    public function getInterface()
    {
        $interface = $this->_MultiSess->get('interface');
        if (empty($interface)) {
            $interface = self::getSetting('FOG_MULTICAST_INTERFACE') ?: 'eth0';
        }
        return $interface;
    }

    /**
     * Get the log file path
     *
     * @return string The log file path
     */
    public function getUDPCastLogFile()
    {
        $logDir = self::getSetting('SERVICE_LOG_PATH') ?: '/opt/fog/log';
        return sprintf(
            '%s/multicast-snapin-%d.log',
            $logDir,
            $this->_intID
        );
    }

    /**
     * Build the udp-sender command (same pattern as MulticastTask)
     *
     * @return string The command
     */
    public function getCMD()
    {
        // Get multicast settings
        list($address, $duplex, $multicastrdv, $maxwait) = self::getSubObjectIDs(
            'Service',
            [
                'name' => [
                    'FOG_MULTICAST_ADDRESS',
                    'FOG_MULTICAST_DUPLEX',
                    'FOG_MULTICAST_RENDEZVOUS',
                    'FOG_UDPCAST_MAXWAIT'
                ]
            ],
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );

        $maxwait = (int) $maxwait;
        if (!$maxwait || $maxwait <= 0) {
            $maxwait = 10;
        }

        // Calculate multicast address based on port (same as images)
        if ($address) {
            $address = long2ip(
                ip2long($address) + (
                    (($this->getPortBase() / 2 + 1)
                    % (int) self::getSetting('FOG_MULTICAST_MAX_SESSIONS'))
                )
            );
        }

        // Get storage node specific settings
        $StorageNode = self::getClass('StorageNode', $this->_storageNodeID);
        $bitrate = $StorageNode->get('bitrate');
        $helloInterval = null; // Can be added if needed

        // Build command
        $buildcmd = [
            UDPSENDERPATH,
            ($helloInterval ?
                sprintf(' --rexmit-hello-interval %s', $helloInterval) :
                null
            ),
            ($bitrate ?
                sprintf(' --max-bitrate %s', $bitrate) :
                null
            ),
            ($this->getInterface() ?
                sprintf(' --interface %s', $this->getInterface()) :
                null
            ),
            sprintf(
                ' --min-receivers %d',
                $this->getClientCount()
            ),
            sprintf(' --max-wait %d', $maxwait * 60),
            ($address ?
                sprintf(' --mcast-data-address %s', $address) :
                null
            ),
            ($multicastrdv ?
                sprintf(' --mcast-rdv-address %s', $multicastrdv) :
                null
            ),
            sprintf(' --portbase %d', $this->getPortBase()),
            sprintf(' %s', $duplex),
            ' --ttl 32',
            ' --nokbd',
            ' --nopointopoint',
        ];

        $buildcmd = array_values(array_filter($buildcmd));

        // Add the snapin file
        $cmd = sprintf(
            '%s --file %s',
            implode('', $buildcmd),
            escapeshellarg($this->getSnapinPath())
        );

        return $cmd;
    }

    /**
     * Start the multicast task
     *
     * @return bool True on success
     */
    public function startTask()
    {
        // Delete old log if exists
        if (file_exists($this->getUDPCastLogFile())) {
            unlink($this->getUDPCastLogFile());
        }

        // Start the task using FOGService method
        $this->startTasking($this->getCMD(), $this->getUDPCastLogFile());
        $this->procRef = array_shift($this->procRef);

        // Mark session as queued
        $this->_MultiSess
            ->set('stateID', self::getQueuedState())
            ->save();

        return $this->isRunning($this->procRef);
    }

    /**
     * Kill the task
     *
     * @return bool True on success
     */
    public function killTask()
    {
        $this->killTasking();

        // Delete log file
        if (file_exists($this->getUDPCastLogFile())) {
            unlink($this->getUDPCastLogFile());
        }

        return true;
    }

    /**
     * Update statistics (progress percentage)
     *
     * @return void
     */
    public function updateStats()
    {
        // For snapins, we can check log file or just mark progress
        // For now, increment percent based on time elapsed
        $startTime = strtotime($this->_MultiSess->get('starttime'));
        $maxWait = (int) self::getSetting('FOG_UDPCAST_MAXWAIT') ?: 10;
        $maxTime = $maxWait * 60;

        $elapsed = time() - $startTime;
        $percent = min(99, (int) (($elapsed / $maxTime) * 100));

        $this->_MultiSess
            ->set('percent', $percent)
            ->save();
    }

    /**
     * Check if session is finished
     *
     * @return bool True if finished
     */
    public function isSessionFinished()
    {
        // Check if max wait time exceeded
        $startTime = strtotime($this->_MultiSess->get('starttime'));
        $maxWait = (int) self::getSetting('FOG_UDPCAST_MAXWAIT') ?: 10;
        $timeout = $startTime + ($maxWait * 60);

        if (time() > $timeout) {
            return true;
        }

        // Check if process is no longer running
        if (!$this->isRunning($this->procRef)) {
            return true;
        }

        return false;
    }
}
