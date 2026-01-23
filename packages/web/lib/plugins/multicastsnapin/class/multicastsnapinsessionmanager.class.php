<?php
/**
 * MulticastSnapinSessionManager class
 *
 * Manages multicast snapin sessions
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinSessionManager class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinSessionManager extends FOGManagerController
{
    /**
     * Install the plugin tables
     *
     * @param string $name The plugin name
     *
     * @return bool True on success
     */
    public function install($name)
    {
        $this->uninstall();

        // Create multicastSnapinSessions table
        $sql = "CREATE TABLE IF NOT EXISTS `multicastSnapinSessions` (
            `mssID` INTEGER NOT NULL AUTO_INCREMENT,
            `mssName` VARCHAR(250) NOT NULL,
            `mssBasePort` INTEGER NOT NULL,
            `mssSnapinID` INTEGER NOT NULL,
            `mssGroupID` INTEGER NOT NULL,
            `mssClients` INTEGER NOT NULL DEFAULT '-1',
            `mssSessClients` INTEGER NOT NULL DEFAULT '0',
            `mssInterface` VARCHAR(15) NOT NULL,
            `mssState` INTEGER NOT NULL DEFAULT '0',
            `mssStartDateTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `mssCompleteDateTime` TIMESTAMP NULL DEFAULT NULL,
            `mssStorageGroupID` INTEGER NOT NULL,
            `mssPercent` INTEGER NOT NULL DEFAULT '0',
            PRIMARY KEY (`mssID`),
            KEY `mssSnapinID` (`mssSnapinID`),
            KEY `mssGroupID` (`mssGroupID`),
            KEY `mssState` (`mssState`),
            KEY `mssStorageGroupID` (`mssStorageGroupID`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC";

        if (!self::$DB->query($sql)) {
            return false;
        }

        // Install service automatically (Option 1)
        $this->_installService();

        return true;
    }

    /**
     * Uninstall the plugin tables
     *
     * @return bool True on success
     */
    public function uninstall()
    {
        // Drop tables
        $sql = "DROP TABLE IF EXISTS `multicastSnapinSessionsAssoc`";
        self::$DB->query($sql);

        $sql = "DROP TABLE IF EXISTS `multicastSnapinSessions`";
        return self::$DB->query($sql);
    }

    /**
     * Get active sessions (queued or in progress)
     *
     * @param int $storageGroupID Optional storage group filter
     *
     * @return array Array of MulticastSnapinSession objects
     */
    public function getActiveSessions($storageGroupID = null)
    {
        $findWhere = array(
            'stateID' => array(0, 1), // Queued or In Progress
        );

        if ($storageGroupID !== null) {
            $findWhere['storagegroupID'] = $storageGroupID;
        }

        return $this->find($findWhere);
    }

    /**
     * Get queued sessions
     *
     * @param int $storageGroupID Optional storage group filter
     *
     * @return array Array of MulticastSnapinSession objects
     */
    public function getQueuedSessions($storageGroupID = null)
    {
        $findWhere = array('stateID' => 0);

        if ($storageGroupID !== null) {
            $findWhere['storagegroupID'] = $storageGroupID;
        }

        return $this->find($findWhere);
    }

    /**
     * Cancel all active sessions
     *
     * @return bool True on success
     */
    public function cancelAllActive()
    {
        $sessions = $this->getActiveSessions();
        foreach ($sessions as $session) {
            $session->cancel();
        }

        return true;
    }

    /**
     * Clean up old completed sessions
     *
     * @param int $daysOld Days old to keep (default 7)
     *
     * @return bool True on success
     */
    public function cleanupOldSessions($daysOld = 7)
    {
        $cutoffDate = self::formatTime(
            sprintf('-%d days', $daysOld),
            'Y-m-d H:i:s'
        );

        return $this->destroy(
            array(
                'stateID' => array(2, 3), // Complete or Cancelled
                'completetime' => $cutoffDate,
            ),
            '<='
        );
    }

    /**
     * Get next available port for multicast (avoids collisions with image multicast)
     *
     * @return int Next available even port number
     */
    public function getNextAvailablePort()
    {
        $basePort = (int) self::getSetting('FOG_UDPCAST_STARTINGPORT');

        // Ensure port is even
        if ($basePort % 2 !== 0) {
            $basePort++;
        }

        // Get all used ports from snapin sessions
        $activeSnapinSessions = $this->getActiveSessions();
        $usedPorts = [];

        foreach ($activeSnapinSessions as $session) {
            $usedPorts[] = (int) $session->get('port');
        }

        // IMPORTANT: Also get ports from image multicast sessions to avoid collision
        try {
            $activeImageSessions = self::getClass('MulticastSessionManager')
                ->getActiveSessions();
            foreach ($activeImageSessions as $session) {
                $usedPorts[] = (int) $session->get('port');
            }
        } catch (Exception $e) {
            // MulticastSessionManager might not exist, ignore
        }

        // Find next available port
        $candidatePort = $basePort;
        $attempts = 0;
        $maxAttempts = 1000; // Prevent infinite loop

        while (in_array($candidatePort, $usedPorts) && $attempts < $maxAttempts) {
            $candidatePort += 2; // Increment by 2 to keep even

            // Wrap around if we exceed max port
            if ($candidatePort > 65534) {
                $candidatePort = 24576; // Min dynamic port (even)
            }

            $attempts++;
        }

        return $candidatePort;
    }

    /**
     * Install service automatically (Option 1)
     * Called when plugin is activated
     *
     * @return void
     */
    private function _installService()
    {
        // Check if running as root
        if (posix_getuid() !== 0) {
            error_log('MulticastSnapin: Cannot install service - not running as root');
            return;
        }

        try {
            $pluginPath = BASEPATH . '/lib/plugins/multicastsnapin';
            $systemdSource = $pluginPath . '/systemd/FOGMulticastSnapinManager.service';
            $systemdTarget = '/lib/systemd/system/FOGMulticastSnapinManager.service';

            // Create service directory
            $serviceDir = '/opt/fog/service/FOGMulticastSnapinManager';
            if (!is_dir($serviceDir)) {
                mkdir($serviceDir, 0755, true);
            }

            // Copy service executable
            $execSource = BASEPATH . '/../service/FOGMulticastSnapinManager/FOGMulticastSnapinManager';
            $execTarget = $serviceDir . '/FOGMulticastSnapinManager';

            if (file_exists($execSource)) {
                copy($execSource, $execTarget);
                chmod($execTarget, 0755);
            }

            // Copy systemd unit file
            if (file_exists($systemdSource)) {
                copy($systemdSource, $systemdTarget);

                // Reload systemd
                exec('systemctl daemon-reload 2>&1', $output, $ret);

                if ($ret === 0) {
                    // Enable and start service
                    exec('systemctl enable FOGMulticastSnapinManager 2>&1', $output, $ret);
                    exec('systemctl start FOGMulticastSnapinManager 2>&1', $output, $ret);

                    error_log('MulticastSnapin: Service installed and started successfully');
                } else {
                    error_log('MulticastSnapin: Failed to reload systemd');
                }
            }
        } catch (Exception $e) {
            error_log('MulticastSnapin: Error installing service - ' . $e->getMessage());
        }
    }
}
