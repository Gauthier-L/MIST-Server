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

        // Create multicastSnapinSessionsAssoc table
        $sql = "CREATE TABLE IF NOT EXISTS `multicastSnapinSessionsAssoc` (
            `mssaID` INTEGER NOT NULL AUTO_INCREMENT,
            `mssID` INTEGER NOT NULL,
            `mssaHostID` INTEGER NOT NULL,
            PRIMARY KEY (`mssaID`),
            KEY `mssID` (`mssID`),
            KEY `mssaHostID` (`mssaHostID`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC";

        return self::$DB->query($sql);
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
     * Get next available port for multicast
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

        // Get all active session ports
        $activeSessions = $this->getActiveSessions();
        $usedPorts = array();

        foreach ($activeSessions as $session) {
            $usedPorts[] = $session->get('port');
        }

        // Find next available port
        $candidatePort = $basePort;
        while (in_array($candidatePort, $usedPorts)) {
            $candidatePort += 2; // Increment by 2 to keep even

            // Wrap around if we exceed max port
            if ($candidatePort > 65534) {
                $candidatePort = 24576; // Min dynamic port (even)
            }
        }

        return $candidatePort;
    }
}
