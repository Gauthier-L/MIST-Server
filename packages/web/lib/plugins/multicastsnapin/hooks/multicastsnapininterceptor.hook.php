<?php
/**
 * MulticastSnapinInterceptor hook
 *
 * Intercepts snapin requests and injects wrapper scripts for multicast sessions
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinInterceptor hook
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinInterceptor extends Hook
{
    /**
     * The name of the hook
     *
     * @var string
     */
    public $name = 'MulticastSnapinInterceptor';

    /**
     * The description of the hook
     *
     * @var string
     */
    public $description = 'Intercepts snapin client requests for multicast sessions';

    /**
     * Is the hook active?
     *
     * @var bool
     */
    public $active = true;

    /**
     * The node this hook operates on
     *
     * @var string
     */
    public $node = 'multicastsnapin';

    /**
     * Initialize hook
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Hook into SNAPIN_NODE to redirect multicast wrapper downloads
        self::$HookManager
            ->register(
                'SNAPIN_NODE',
                array($this, 'interceptSnapinNode')
            );
    }

    /**
     * Intercept SNAPIN_NODE to provide wrapper downloads for multicast sessions
     *
     * This hook detects if a snapin task belongs to a multicast session
     * and redirects the download to our wrapper serving endpoint.
     *
     * @param mixed $arguments Hook arguments with Host, Snapin, StorageNode
     *
     * @return void
     */
    public function interceptSnapinNode($arguments)
    {
        // Get task ID from request (available during download phase)
        $taskID = isset($_REQUEST['taskid']) ? (int)$_REQUEST['taskid'] : null;

        if (!$taskID) {
            // During checkin phase, we can't intercept individual tasks yet
            // The wrapper will be detected during download phase
            return;
        }

        // Check if this task has a wrapper entry
        $WrapperEntry = self::getClass('MulticastSnapinWrapperEntryManager')
            ->getBySnapinTask($taskID);

        if (!$WrapperEntry || !$WrapperEntry->isValid()) {
            // Not a multicast wrapper task, let FOG handle normally
            return;
        }

        // This is a multicast wrapper task - create a virtual storage node
        // that points to our wrapper endpoint
        $serverIP = self::getSetting('FOG_TFTP_HOST');
        $webRoot = self::getSetting('FOG_WEB_ROOT');

        // Build wrapper URL
        $wrapperURL = sprintf(
            'http://%s%s/service/multicast-snapin-wrapper.php?taskid=%d',
            $serverIP,
            $webRoot,
            $taskID
        );

        // Create virtual storage node
        $VirtualNode = new VirtualStorageNode($serverIP, $wrapperURL);
        $VirtualNode->location_url = $wrapperURL;

        // Replace StorageNode in arguments
        $arguments['StorageNode'] = $VirtualNode;

        // Also modify the Snapin object to have wrapper metadata
        $Snapin = $arguments['Snapin'];
        $osType = $WrapperEntry->get('ostype');

        $wrapperFilename = sprintf(
            'mc-wrapper-%d.%s',
            $taskID,
            $osType === 'windows' ? 'ps1' : 'sh'
        );

        // Override snapin properties
        $Snapin->set('file', $wrapperFilename);
        $Snapin->set('hash', $WrapperEntry->get('hash'));

        if ($osType === 'windows') {
            $Snapin->set('runWith', 'powershell.exe');
            $Snapin->set('runWithArgs', '-ExecutionPolicy Bypass -NoProfile -File');
        } else {
            $Snapin->set('runWith', '/bin/bash');
            $Snapin->set('runWithArgs', '');
        }

        $Snapin->set('args', '');
        $Snapin->set('packtype', false);
    }
}
