<?php
/**
 * MulticastSnapinWrapperEntryManager class
 *
 * Manages multicast snapin wrapper entries
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinWrapperEntryManager class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinWrapperEntryManager extends FOGManagerController
{
    /**
     * Get wrapper by session and host
     *
     * @param int $sessionID The session ID
     * @param int $hostID    The host ID
     *
     * @return object|null The wrapper entry or null
     */
    public function getBySessionAndHost($sessionID, $hostID)
    {
        $wrappers = $this->find(
            array(
                'sessionID' => $sessionID,
                'hostID' => $hostID
            )
        );

        return count($wrappers) > 0 ? $wrappers[0] : null;
    }

    /**
     * Get wrapper by snapin task ID
     *
     * @param int $snapintaskID The snapin task ID
     *
     * @return object|null The wrapper entry or null
     */
    public function getBySnapinTask($snapintaskID)
    {
        $wrappers = $this->find(
            array('snapintaskID' => $snapintaskID)
        );

        return count($wrappers) > 0 ? $wrappers[0] : null;
    }

    /**
     * Delete all wrappers for a session
     *
     * @param int $sessionID The session ID
     *
     * @return bool True on success
     */
    public function deleteBySession($sessionID)
    {
        return $this->destroy(array('sessionID' => $sessionID));
    }

    /**
     * Get all wrappers for a session
     *
     * @param int $sessionID The session ID
     *
     * @return array Array of wrapper entries
     */
    public function getBySession($sessionID)
    {
        return $this->find(array('sessionID' => $sessionID));
    }
}
