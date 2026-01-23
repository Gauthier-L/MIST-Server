<?php
/**
 * MulticastSnapinTask class
 *
 * Represents a running multicast snapin task
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinTask class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinTask extends FOGClient
{
    /**
     * The multicast session
     *
     * @var object
     */
    private $_session;

    /**
     * The snapin object
     *
     * @var object
     */
    private $_snapin;

    /**
     * The storage node
     *
     * @var object
     */
    private $_storageNode;

    /**
     * The process reference
     *
     * @var resource
     */
    private $_procRef;

    /**
     * The log file path
     *
     * @var string
     */
    private $_logPath;

    /**
     * Constructor
     *
     * @param object $session The multicast session object
     */
    public function __construct($session)
    {
        parent::__construct();

        $this->_session = $session;
        $this->_snapin = $session->getSnapin();
        $this->_storageNode = $session->getStorageNode();

        // Set log path
        $logDir = self::getSetting('SERVICE_LOG_PATH') ?: '/opt/fog/log';
        $logFile = sprintf(
            'multicast-snapin-%d.log',
            $session->get('id')
        );
        $this->_logPath = sprintf('%s/%s', $logDir, $logFile);
    }

    /**
     * Get the command to execute
     *
     * @return string The udp-sender command
     */
    public function getCMD()
    {
        $snapinFile = $this->_getSnapinFilePath();
        if (!$snapinFile || !file_exists($snapinFile)) {
            throw new Exception(
                sprintf(
                    'Snapin file not found: %s',
                    $snapinFile ?: 'unknown'
                )
            );
        }

        $interface = $this->_session->get('interface');
        $port = $this->_session->get('port');
        $clients = $this->_session->get('clients');

        // Get multicast settings
        $multicastAddress = self::getSetting('FOG_MULTICAST_ADDRESS') ?: '224.0.0.1';
        $duplex = self::getSetting('FOG_MULTICAST_DUPLEX') ? '--duplex' : '';
        $rendezvous = self::getSetting('FOG_MULTICAST_RENDEZVOUS');
        $maxWait = (int) self::getSetting('FOG_UDPCAST_MAXWAIT') ?: 10;
        $maxWaitSeconds = $maxWait * 60;

        // Get storage node specific settings
        $nodeInterface = $this->_storageNode->get('interface') ?: $interface;
        $bitrate = $this->_storageNode->get('bitrate');

        // Build command
        $cmd = array(
            UDPSENDERPATH,
            '--interface', escapeshellarg($nodeInterface),
            '--min-receivers', (int) $clients,
            '--max-wait', $maxWaitSeconds,
            '--portbase', (int) $port,
            '--mcast-data-address', escapeshellarg($multicastAddress),
            '--ttl', 32,
            '--nokbd',
            '--nopointopoint',
        );

        // Add optional parameters
        if ($rendezvous) {
            $cmd[] = '--mcast-rdv-address';
            $cmd[] = escapeshellarg($rendezvous);
        }

        if ($duplex) {
            $cmd[] = $duplex;
        }

        if ($bitrate) {
            $cmd[] = '--max-bitrate';
            $cmd[] = escapeshellarg($bitrate);
        }

        // Add file to send
        $cmd[] = '--file';
        $cmd[] = escapeshellarg($snapinFile);

        // Redirect output to log
        $cmd[] = '2>&1';
        $cmd[] = '>';
        $cmd[] = escapeshellarg($this->_logPath);
        $cmd[] = '&';

        return implode(' ', $cmd);
    }

    /**
     * Get the snapin file path on storage node
     *
     * @return string|bool The file path or false
     */
    private function _getSnapinFilePath()
    {
        if (!$this->_snapin || !$this->_snapin->isValid()) {
            return false;
        }

        $snapinPath = $this->_storageNode->get('snapinpath');
        $snapinFile = $this->_snapin->get('file');

        if (!$snapinPath || !$snapinFile) {
            return false;
        }

        return sprintf('%s/%s', rtrim($snapinPath, '/'), $snapinFile);
    }

    /**
     * Start the multicast task
     *
     * @return bool True on success
     */
    public function startTask()
    {
        try {
            $cmd = $this->getCMD();

            // Execute the command
            $this->_procRef = proc_open(
                $cmd,
                array(),
                $pipes
            );

            if (!is_resource($this->_procRef)) {
                throw new Exception('Failed to start udp-sender process');
            }

            // Mark session as in progress
            $this->_session->markInProgress();

            self::$EventManager
                ->notify(
                    'MULTICAST_SNAPIN_SESSION_START',
                    array('MulticastSnapinSession' => &$this->_session)
                );

            return true;
        } catch (Exception $e) {
            $this->_session->set('stateID', 3); // Cancelled/Error
            $this->_session->save();

            self::$EventManager
                ->notify(
                    'MULTICAST_SNAPIN_SESSION_ERROR',
                    array(
                        'MulticastSnapinSession' => &$this->_session,
                        'error' => $e->getMessage(),
                    )
                );

            return false;
        }
    }

    /**
     * Check if the task is still running
     *
     * @return bool True if running
     */
    public function isRunning()
    {
        if (!is_resource($this->_procRef)) {
            return false;
        }

        $status = proc_get_status($this->_procRef);
        return $status && $status['running'];
    }

    /**
     * Stop the task
     *
     * @return bool True on success
     */
    public function stopTask()
    {
        if (!is_resource($this->_procRef)) {
            return false;
        }

        // Terminate the process
        proc_terminate($this->_procRef);
        proc_close($this->_procRef);
        $this->_procRef = null;

        return true;
    }

    /**
     * Get the log file path
     *
     * @return string The log file path
     */
    public function getLogPath()
    {
        return $this->_logPath;
    }

    /**
     * Get the session
     *
     * @return object The session object
     */
    public function getSession()
    {
        return $this->_session;
    }
}
