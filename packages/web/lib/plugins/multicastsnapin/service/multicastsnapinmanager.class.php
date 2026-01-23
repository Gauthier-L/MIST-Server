<?php
/**
 * MulticastSnapinManager service class
 *
 * Service that manages multicast snapin sessions
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinManager service class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinManager extends FOGService
{
    /**
     * Array of active tasks
     *
     * @var array
     */
    private $_activeTasks = array();

    /**
     * Maximum number of concurrent sessions per node
     *
     * @var int
     */
    private $_maxSessions = 5;

    /**
     * Initialize the service
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Set service name
        static::$name = 'MulticastSnapinManager';
        static::$description = 'Manages multicast snapin deployment sessions';

        // Check for udp-sender
        if (!file_exists(UDPSENDERPATH)) {
            static::$log->error(
                sprintf(
                    'udp-sender not found at %s',
                    UDPSENDERPATH
                )
            );
            exit(1);
        }

        // Get max sessions from settings
        $this->_maxSessions = (int) self::getSetting('FOG_MULTICAST_MAX_SESSIONS') ?: 5;
    }

    /**
     * Service loop - called repeatedly
     *
     * @return void
     */
    public function serviceRun()
    {
        // Clean up completed tasks
        $this->_cleanupTasks();

        // Process queued sessions
        $this->_processQueuedSessions();

        // Monitor active sessions
        $this->_monitorActiveSessions();

        // Sleep before next iteration
        $sleepTime = (int) self::getSetting('MULTICASTSLEEPTIME') ?: 10;
        sleep($sleepTime);
    }

    /**
     * Clean up completed tasks
     *
     * @return void
     */
    private function _cleanupTasks()
    {
        foreach ($this->_activeTasks as $sessionID => $task) {
            if (!$task->isRunning()) {
                $session = $task->getSession();

                // Mark as complete
                $session->markComplete();

                // Stop the task
                $task->stopTask();

                // Remove from active tasks
                unset($this->_activeTasks[$sessionID]);

                static::$log->info(
                    sprintf(
                        'Session %d (%s) completed',
                        $sessionID,
                        $session->get('name')
                    )
                );

                self::$EventManager
                    ->notify(
                        'MULTICAST_SNAPIN_SESSION_COMPLETE',
                        array('MulticastSnapinSession' => &$session)
                    );
            }
        }
    }

    /**
     * Process queued sessions
     *
     * @return void
     */
    private function _processQueuedSessions()
    {
        // Get all storage groups
        $StorageGroups = self::getClass('StorageGroupManager')->find();

        foreach ((array) $StorageGroups as $StorageGroup) {
            if (!$StorageGroup->isValid()) {
                continue;
            }

            // Only process on master nodes
            $masterNode = $StorageGroup->getMasterStorageNode();
            if (!$masterNode || !$masterNode->isValid()) {
                continue;
            }

            // Check if we're on this master node
            if (!$this->_isCurrentNode($masterNode)) {
                continue;
            }

            // Check available slots
            $activeCount = $this->_getActiveSessionCount($StorageGroup->get('id'));
            if ($activeCount >= $this->_maxSessions) {
                static::$log->debug(
                    sprintf(
                        'Storage group %d at max capacity (%d/%d)',
                        $StorageGroup->get('id'),
                        $activeCount,
                        $this->_maxSessions
                    )
                );
                continue;
            }

            // Get queued sessions for this storage group
            $sessions = self::getClass('MulticastSnapinSessionManager')
                ->getQueuedSessions($StorageGroup->get('id'));

            foreach ((array) $sessions as $session) {
                if (!$session->isValid()) {
                    continue;
                }

                // Check if we still have slots
                if ($activeCount >= $this->_maxSessions) {
                    break;
                }

                // Validate session
                if (!$this->_validateSession($session)) {
                    $session->cancel();
                    continue;
                }

                // Start the session
                if ($this->_startSession($session)) {
                    $activeCount++;
                }
            }
        }
    }

    /**
     * Monitor active sessions
     *
     * @return void
     */
    private function _monitorActiveSessions()
    {
        foreach ($this->_activeTasks as $sessionID => $task) {
            $session = $task->getSession();

            // Check for timeout
            $startTime = strtotime($session->get('starttime'));
            $maxWait = (int) self::getSetting('FOG_UDPCAST_MAXWAIT') ?: 10;
            $timeout = $startTime + ($maxWait * 60);

            if (time() > $timeout) {
                static::$log->warning(
                    sprintf(
                        'Session %d (%s) timed out',
                        $sessionID,
                        $session->get('name')
                    )
                );

                $task->stopTask();
                $session->cancel();
                unset($this->_activeTasks[$sessionID]);

                self::$EventManager
                    ->notify(
                        'MULTICAST_SNAPIN_SESSION_TIMEOUT',
                        array('MulticastSnapinSession' => &$session)
                    );
            }
        }
    }

    /**
     * Check if current node matches storage node
     *
     * @param object $storageNode The storage node to check
     *
     * @return bool True if current node
     */
    private function _isCurrentNode($storageNode)
    {
        $currentNode = self::getClass('StorageNode')
            ->set('ip', self::getSetting('FOG_TFTP_HOST'))
            ->load('ip');

        return $currentNode->isValid()
            && $currentNode->get('id') === $storageNode->get('id');
    }

    /**
     * Get count of active sessions for storage group
     *
     * @param int $storageGroupID The storage group ID
     *
     * @return int Count of active sessions
     */
    private function _getActiveSessionCount($storageGroupID)
    {
        $count = 0;
        foreach ($this->_activeTasks as $task) {
            $session = $task->getSession();
            if ($session->get('storagegroupID') == $storageGroupID) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Validate a session before starting
     *
     * @param object $session The session to validate
     *
     * @return bool True if valid
     */
    private function _validateSession($session)
    {
        // Check snapin exists
        $snapin = $session->getSnapin();
        if (!$snapin || !$snapin->isValid()) {
            static::$log->error(
                sprintf(
                    'Session %d: Invalid snapin',
                    $session->get('id')
                )
            );
            return false;
        }

        // Check port is even
        $port = $session->get('port');
        if ($port % 2 !== 0) {
            static::$log->error(
                sprintf(
                    'Session %d: Port %d is not even',
                    $session->get('id'),
                    $port
                )
            );
            return false;
        }

        // Check clients count
        $clients = $session->get('clients');
        if ($clients < 1) {
            static::$log->error(
                sprintf(
                    'Session %d: Invalid client count %d',
                    $session->get('id'),
                    $clients
                )
            );
            return false;
        }

        // Check storage node
        $storageNode = $session->getStorageNode();
        if (!$storageNode || !$storageNode->isValid()) {
            static::$log->error(
                sprintf(
                    'Session %d: Invalid storage node',
                    $session->get('id')
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Start a multicast session
     *
     * @param object $session The session to start
     *
     * @return bool True on success
     */
    private function _startSession($session)
    {
        try {
            static::$log->info(
                sprintf(
                    'Starting session %d (%s) - Snapin: %s, Clients: %d, Port: %d',
                    $session->get('id'),
                    $session->get('name'),
                    $session->getSnapin()->get('name'),
                    $session->get('clients'),
                    $session->get('port')
                )
            );

            // Create task
            $task = new MulticastSnapinTask($session);

            // Start the task
            if (!$task->startTask()) {
                throw new Exception('Failed to start task');
            }

            // Add to active tasks
            $this->_activeTasks[$session->get('id')] = $task;

            return true;
        } catch (Exception $e) {
            static::$log->error(
                sprintf(
                    'Failed to start session %d: %s',
                    $session->get('id'),
                    $e->getMessage()
                )
            );

            $session->cancel();
            return false;
        }
    }
}
