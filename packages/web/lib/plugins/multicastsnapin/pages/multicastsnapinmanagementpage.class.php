<?php
/**
 * Multicast Snapin Management Page
 *
 * Provides web interface for managing multicast snapin sessions
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Multicast Snapin Management Page
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinManagementPage extends FOGPage
{
    /**
     * The node name
     *
     * @var string
     */
    public $node = 'multicastsnapin';

    /**
     * Constructor
     *
     * @param string $name The page name
     *
     * @return void
     */
    public function __construct($name = '')
    {
        $this->name = _('Multicast Snapin Management');
        parent::__construct($this->name);

        // Define menu items
        $this->menu = array(
            'list' => sprintf(
                self::$foglang['ListAll'],
                _('Sessions')
            ),
            'new' => sprintf(
                self::$foglang['CreateNew'],
                _('Session')
            ),
            'active' => _('Active Sessions'),
        );

        // Set header data for list view
        $this->headerData = array(
            '<input type="checkbox" class="toggle-checkbox" />',
            _('Session Name'),
            _('Snapin'),
            _('Group'),
            _('State'),
            _('Clients'),
            _('Progress'),
            _('Created'),
        );

        // Set template data
        $this->templates = array(
            '<input type="checkbox" class="toggle-action" name="multicastsnapin[]" value="${id}" />',
            '<a href="?node=${node}&sub=edit&id=${id}">${name}</a>',
            '${snapin_name}',
            '${group_name}',
            '${state_name}',
            '${clients} / ${sessclients}',
            '${percent}%',
            '${starttime}',
        );

        // Set attributes
        $this->attributes = array(
            array(
                'class' => 'filter-false',
                'width' => 16
            ),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
            array(),
        );

        self::$HookManager->processEvent(
            'MULTICASTSNAPIN_HEADER_DATA',
            array(
                'headerData' => &$this->headerData,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
            )
        );
    }

    /**
     * List all sessions
     *
     * @return void
     */
    public function index()
    {
        $this->title = _('All Multicast Snapin Sessions');

        // Get sessions
        $Sessions = self::getClass('MulticastSnapinSessionManager')->find();

        // Format data
        foreach ((array) $Sessions as $Session) {
            if (!$Session->isValid()) {
                continue;
            }

            $Snapin = $Session->getSnapin();
            $Group = $Session->getGroup();

            $this->data[] = array(
                'id' => $Session->get('id'),
                'node' => $this->node,
                'name' => $Session->get('name'),
                'snapin_name' => $Snapin && $Snapin->isValid()
                    ? $Snapin->get('name')
                    : _('Unknown'),
                'group_name' => $Group && $Group->isValid()
                    ? $Group->get('name')
                    : _('Unknown'),
                'state_name' => $Session->getStateName(),
                'clients' => $Session->get('clients'),
                'sessclients' => $Session->get('sessclients'),
                'percent' => $Session->get('percent'),
                'starttime' => $this->formatTime(
                    $Session->get('starttime'),
                    'Y-m-d H:i:s'
                ),
            );

            unset($Session, $Snapin, $Group);
        }

        self::$HookManager->processEvent(
            'MULTICASTSNAPIN_DATA',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
            )
        );

        $this->render();
    }

    /**
     * Show active sessions
     *
     * @return void
     */
    public function active()
    {
        $this->title = _('Active Multicast Snapin Sessions');

        // Get active sessions
        $Sessions = self::getClass('MulticastSnapinSessionManager')
            ->getActiveSessions();

        if (count($Sessions) === 0) {
            printf(
                '<div class="info-box">%s</div>',
                _('No active sessions')
            );
            return;
        }

        // Format data
        foreach ((array) $Sessions as $Session) {
            if (!$Session->isValid()) {
                continue;
            }

            $Snapin = $Session->getSnapin();
            $Group = $Session->getGroup();

            $this->data[] = array(
                'id' => $Session->get('id'),
                'node' => $this->node,
                'name' => $Session->get('name'),
                'snapin_name' => $Snapin && $Snapin->isValid()
                    ? $Snapin->get('name')
                    : _('Unknown'),
                'group_name' => $Group && $Group->isValid()
                    ? $Group->get('name')
                    : _('Unknown'),
                'state_name' => $Session->getStateName(),
                'clients' => $Session->get('clients'),
                'sessclients' => $Session->get('sessclients'),
                'percent' => $Session->get('percent'),
                'starttime' => $this->formatTime(
                    $Session->get('starttime'),
                    'Y-m-d H:i:s'
                ),
            );

            unset($Session, $Snapin, $Group);
        }

        self::$HookManager->processEvent(
            'MULTICASTSNAPIN_DATA',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
            )
        );

        $this->render();
    }

    /**
     * Create new session form
     *
     * @return void
     */
    public function new_form()
    {
        $this->title = _('Create New Multicast Snapin Session');

        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );

        $this->templates = array(
            '${field}',
            '${input}',
        );

        // Get snapins for dropdown
        $Snapins = self::getClass('SnapinManager')->find(
            array('isEnabled' => 1)
        );
        $snapinOptions = array();
        foreach ((array) $Snapins as $Snapin) {
            if (!$Snapin->isValid()) {
                continue;
            }
            $snapinOptions[$Snapin->get('id')] = $Snapin->get('name');
        }

        // Get groups for dropdown (only groups with > 2 hosts)
        $Groups = self::getClass('GroupManager')->find();
        $groupOptions = array();
        foreach ((array) $Groups as $Group) {
            if (!$Group->isValid()) {
                continue;
            }

            // Get host count for this group
            $hostCount = self::getClass('GroupAssociationManager')
                ->count(array('groupID' => $Group->get('id')));

            // Only include groups with at least 3 hosts
            if ($hostCount >= 3) {
                $groupOptions[$Group->get('id')] = sprintf(
                    '%s (%d hosts)',
                    $Group->get('name'),
                    $hostCount
                );
            }
        }

        // Get storage groups for dropdown
        $StorageGroups = self::getClass('StorageGroupManager')->find();
        $storageGroupOptions = array();
        foreach ((array) $StorageGroups as $StorageGroup) {
            if (!$StorageGroup->isValid()) {
                continue;
            }
            $storageGroupOptions[$StorageGroup->get('id')] = $StorageGroup->get('name');
        }

        $fields = array(
            _('Snapin') => self::getClass('Process')
                ->select('snapinID', $snapinOptions)
                ->required('required'),
            _('Host Group') => self::getClass('Process')
                ->select('groupID', $groupOptions)
                ->required('required'),
            _('Storage Group') => self::getClass('Process')
                ->select('storagegroupID', $storageGroupOptions)
                ->required('required'),
            '&nbsp;' => self::getClass('Process')
                ->input('add', _('Create Multicast Session'))
                ->type('submit'),
        );

        // Info message about automatic port allocation
        printf(
            '<div class="info-box"><strong>%s:</strong> %s</div>',
            _('Note'),
            _('Port will be allocated automatically to avoid conflicts with image multicast sessions')
        );

        // Add info message if no groups available
        if (empty($groupOptions)) {
            printf(
                '<div class="info-box">%s</div>',
                _('No groups with at least 3 hosts available. Please create a group with at least 3 hosts to use multicast deployment.')
            );
        }

        foreach ($fields as $field => $input) {
            $this->data[] = array(
                'field' => $field,
                'input' => $input,
            );
        }

        self::$HookManager->processEvent(
            'MULTICASTSNAPIN_NEW',
            array(
                'data' => &$this->data,
                'templates' => &$this->templates,
                'attributes' => &$this->attributes,
            )
        );

        printf(
            '<form method="post" action="%s">',
            $this->formAction
        );

        $this->render();

        echo '</form>';
    }

    /**
     * Create new session POST
     *
     * @return void
     */
    public function new_post()
    {
        self::$HookManager->processEvent('MULTICASTSNAPIN_NEW_POST');

        try {
            // Validate inputs
            $snapinID = (int) ($_POST['snapinID'] ?? 0);
            $groupID = (int) ($_POST['groupID'] ?? 0);
            $storagegroupID = (int) ($_POST['storagegroupID'] ?? 0);

            // Validate snapin
            if ($snapinID < 1) {
                throw new Exception(_('Please select a snapin'));
            }

            $Snapin = self::getClass('Snapin', $snapinID);
            if (!$Snapin->isValid()) {
                throw new Exception(_('Invalid snapin selected'));
            }

            // Validate group
            if ($groupID < 1) {
                throw new Exception(_('Please select a host group'));
            }

            $Group = self::getClass('Group', $groupID);
            if (!$Group->isValid()) {
                throw new Exception(_('Invalid group selected'));
            }

            // Count hosts in group
            $hostCount = self::getClass('GroupAssociationManager')
                ->count(array('groupID' => $groupID));

            if ($hostCount < 3) {
                throw new Exception(
                    sprintf(
                        _('Group must have at least 3 hosts for multicast deployment. This group has %d host(s).'),
                        $hostCount
                    )
                );
            }

            // Validate storage group
            if ($storagegroupID < 1) {
                throw new Exception(_('Please select a storage group'));
            }

            $StorageGroup = self::getClass('StorageGroup', $storagegroupID);
            if (!$StorageGroup->isValid()) {
                throw new Exception(_('Invalid storage group selected'));
            }

            // Allocate port automatically (avoids collisions with image multicast)
            $port = self::getClass('MulticastSnapinSessionManager')
                ->getNextAvailablePort();

            // Generate session name automatically: "SnapinName - GroupName"
            $sessionName = sprintf(
                '%s - %s',
                $Snapin->get('name'),
                $Group->get('name')
            );

            // Get network interface from storage node or use default
            $interface = null;
            $masterNode = $StorageGroup->getMasterStorageNode();
            if ($masterNode && $masterNode->isValid()) {
                $interface = $masterNode->get('interface');
            }
            // Fallback to FOG default interface setting
            if (empty($interface)) {
                $interface = self::getSetting('FOG_MULTICAST_INTERFACE') ?: 'eth0';
            }

            // Create session
            $Session = self::getClass('MulticastSnapinSession')
                ->set('name', $sessionName)
                ->set('snapinID', $snapinID)
                ->set('groupID', $groupID)
                ->set('storagegroupID', $storagegroupID)
                ->set('clients', $hostCount)
                ->set('sessclients', 0)
                ->set('port', $port)
                ->set('interface', $interface)
                ->set('stateID', 0) // Queued
                ->set('percent', 0)
                ->set('starttime', self::formatTime('now', 'Y-m-d H:i:s'));

            if (!$Session->save()) {
                throw new Exception(_('Failed to create session'));
            }

            // Auto-create SnapinJobs and SnapinTasks for each host
            $this->_createSnapinTasksForSession($Session, $Group, $Snapin, $masterNode);

            self::$HookManager->processEvent(
                'MULTICASTSNAPIN_ADD',
                array('MulticastSnapinSession' => &$Session)
            );

            $this->setMessage(
                sprintf(
                    _('Multicast session "%s" created successfully for %d hosts'),
                    $sessionName,
                    $hostCount
                )
            );

            $this->redirect(
                sprintf(
                    '?node=%s&sub=edit&id=%d',
                    $this->node,
                    $Session->get('id')
                )
            );
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
            $this->redirect($this->formAction);
        }
    }

    /**
     * Edit session form
     *
     * @return void
     */
    public function edit()
    {
        $SessionID = (int) $_GET['id'];
        $Session = self::getClass('MulticastSnapinSession', $SessionID);

        if (!$Session->isValid()) {
            $this->setMessage(_('Invalid session'));
            $this->redirect(sprintf('?node=%s', $this->node));
            return;
        }

        $this->title = sprintf(
            '%s: %s',
            _('Edit Session'),
            $Session->get('name')
        );

        $this->information($Session);
    }

    /**
     * Display session information
     *
     * @param object $Session The session object
     *
     * @return void
     */
    public function information($Session)
    {
        unset($this->headerData);
        $this->attributes = array(
            array(),
            array(),
        );

        $this->templates = array(
            '${field}',
            '${value}',
        );

        $Snapin = $Session->getSnapin();
        $Group = $Session->getGroup();
        $StorageGroup = $Session->getStorageGroup();
        $StorageNode = $Session->getStorageNode();

        $fields = array(
            _('Session ID') => $Session->get('id'),
            _('Session Name') => $Session->get('name'),
            _('Snapin') => $Snapin && $Snapin->isValid()
                ? $Snapin->get('name')
                : _('Unknown'),
            _('Host Group') => $Group && $Group->isValid()
                ? $Group->get('name')
                : _('Unknown'),
            _('Storage Group') => $StorageGroup && $StorageGroup->isValid()
                ? $StorageGroup->get('name')
                : _('Unknown'),
            _('Storage Node') => $StorageNode && $StorageNode->isValid()
                ? sprintf(
                    '%s (%s)',
                    $StorageNode->get('name'),
                    $StorageNode->get('ip')
                )
                : _('Unknown'),
            _('State') => $Session->getStateName(),
            _('Clients Expected') => $Session->get('clients'),
            _('Clients Joined') => $Session->get('sessclients'),
            _('Progress') => sprintf('%d%%', $Session->get('percent')),
            _('Port') => $Session->get('port'),
            _('Interface') => $Session->get('interface'),
            _('Started') => $this->formatTime(
                $Session->get('starttime'),
                'Y-m-d H:i:s'
            ),
            _('Completed') => $Session->get('completetime')
                ? $this->formatTime(
                    $Session->get('completetime'),
                    'Y-m-d H:i:s'
                )
                : _('N/A'),
        );

        foreach ($fields as $field => $value) {
            $this->data[] = array(
                'field' => $field,
                'value' => $value,
            );
        }

        self::$HookManager->processEvent(
            'MULTICASTSNAPIN_EDIT',
            array(
                'data' => &$this->data,
                'Session' => &$Session,
            )
        );

        $this->render();

        // Show cancel button for active sessions
        if ($Session->isQueued() || $Session->isInProgress()) {
            printf(
                '<br/><form method="post" action="?node=%s&sub=cancel&id=%d">',
                $this->node,
                $Session->get('id')
            );
            printf(
                '<input type="submit" value="%s" />',
                _('Cancel Session')
            );
            echo '</form>';
        }
    }

    /**
     * Cancel a session
     *
     * @return void
     */
    public function cancel()
    {
        $SessionID = (int) $_GET['id'];
        $Session = self::getClass('MulticastSnapinSession', $SessionID);

        if (!$Session->isValid()) {
            $this->setMessage(_('Invalid session'));
            $this->redirect(sprintf('?node=%s', $this->node));
            return;
        }

        try {
            $Session->cancel();

            self::$HookManager->processEvent(
                'MULTICASTSNAPIN_CANCEL',
                array('MulticastSnapinSession' => &$Session)
            );

            $this->setMessage(
                sprintf(
                    _('Session %s cancelled'),
                    $Session->get('name')
                )
            );
        } catch (Exception $e) {
            $this->setMessage($e->getMessage());
        }

        $this->redirect(sprintf('?node=%s', $this->node));
    }

    /**
     * Create SnapinJobs and SnapinTasks for all hosts in a multicast session
     *
     * @param object $Session     The multicast session
     * @param object $Group       The host group
     * @param object $Snapin      The snapin to deploy
     * @param object $StorageNode The master storage node
     *
     * @return void
     * @throws Exception On failure
     */
    private function _createSnapinTasksForSession($Session, $Group, $Snapin, $StorageNode)
    {
        $sessionID = $Session->get('id');
        $snapinID = $Snapin->get('id');
        $groupID = $Group->get('id');

        // Get all hosts in the group
        $hostIDs = self::getSubObjectIDs(
            'GroupAssociation',
            array('groupID' => $groupID),
            'hostID'
        );

        if (empty($hostIDs)) {
            throw new Exception(_('No hosts found in group'));
        }

        $hosts = self::getClass('HostManager')->find(
            array('id' => $hostIDs)
        );

        $createdJobs = 0;
        $createdTasks = 0;
        $now = self::niceDate()->format('Y-m-d H:i:s');
        $serverIP = self::getSetting('FOG_TFTP_HOST');

        foreach ($hosts as &$Host) {
            if (!$Host->isValid()) {
                continue;
            }

            try {
                // Create SnapinJob for this host
                $SnapinJob = self::getClass('SnapinJob')
                    ->set('hostID', $Host->get('id'))
                    ->set('stateID', self::getQueuedState())
                    ->set('createdTime', $now);

                if (!$SnapinJob->save()) {
                    throw new Exception(
                        sprintf(
                            _('Failed to create SnapinJob for host %s'),
                            $Host->get('name')
                        )
                    );
                }

                $createdJobs++;

                // Create SnapinTask pointing to the real snapin
                $SnapinTask = self::getClass('SnapinTask')
                    ->set('jobID', $SnapinJob->get('id'))
                    ->set('snapinID', $snapinID)
                    ->set('stateID', self::getQueuedState())
                    ->set('checkin', $now);

                if (!$SnapinTask->save()) {
                    throw new Exception(
                        sprintf(
                            _('Failed to create SnapinTask for host %s'),
                            $Host->get('name')
                        )
                    );
                }

                $createdTasks++;

                // Detect host OS type (Windows or Linux)
                // Use inventory or default to Windows
                $osType = 'windows';
                $osName = strtolower($Host->get('osname'));
                if (strpos($osName, 'linux') !== false
                    || strpos($osName, 'ubuntu') !== false
                    || strpos($osName, 'debian') !== false
                    || strpos($osName, 'centos') !== false
                    || strpos($osName, 'redhat') !== false
                ) {
                    $osType = 'linux';
                }

                // Generate wrapper script
                if ($osType === 'windows') {
                    $wrapperScript = MulticastSnapinWrapper::generateWindowsScript(
                        $Session,
                        $Snapin,
                        $SnapinTask->get('id'),
                        $serverIP
                    );
                } else {
                    $wrapperScript = MulticastSnapinWrapper::generateLinuxScript(
                        $Session,
                        $Snapin,
                        $SnapinTask->get('id'),
                        $serverIP
                    );
                }

                // Calculate wrapper hash (SHA512 to match FOG's hash system)
                $wrapperHash = hash('sha512', $wrapperScript);

                // Create wrapper entry
                $WrapperEntry = self::getClass('MulticastSnapinWrapperEntry')
                    ->set('sessionID', $sessionID)
                    ->set('hostID', $Host->get('id'))
                    ->set('snapinjobID', $SnapinJob->get('id'))
                    ->set('snapintaskID', $SnapinTask->get('id'))
                    ->set('script', $wrapperScript)
                    ->set('hash', $wrapperHash)
                    ->set('ostype', $osType)
                    ->set('createdtime', $now);

                if (!$WrapperEntry->save()) {
                    throw new Exception(
                        sprintf(
                            _('Failed to create wrapper entry for host %s'),
                            $Host->get('name')
                        )
                    );
                }

            } catch (Exception $e) {
                // Log error but continue with other hosts
                self::getClass('MulticastSnapinManager')->outall(
                    sprintf(
                        ' * ERROR creating task for host %s: %s',
                        $Host->get('name'),
                        $e->getMessage()
                    )
                );
                continue;
            }
        }
        unset($Host);

        if ($createdJobs == 0 || $createdTasks == 0) {
            throw new Exception(
                _('Failed to create any snapin tasks for the session')
            );
        }

        self::getClass('MulticastSnapinManager')->outall(
            sprintf(
                ' * Created %d SnapinJobs and %d SnapinTasks for session %d',
                $createdJobs,
                $createdTasks,
                $sessionID
            )
        );
    }
}
