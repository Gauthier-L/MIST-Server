<?php
/**
 * Multicast Snapin Management Page
 *
 * Provides web interface for managing multicast snapin sessions
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Multicast Snapin Management Page
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
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

            $this->data[] = array(
                'id' => $Session->get('id'),
                'node' => $this->node,
                'name' => $Session->get('name'),
                'snapin_name' => $Snapin && $Snapin->isValid()
                    ? $Snapin->get('name')
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

            unset($Session, $Snapin);
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

            $this->data[] = array(
                'id' => $Session->get('id'),
                'node' => $this->node,
                'name' => $Session->get('name'),
                'snapin_name' => $Snapin && $Snapin->isValid()
                    ? $Snapin->get('name')
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

            unset($Session, $Snapin);
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

        // Get storage groups for dropdown
        $StorageGroups = self::getClass('StorageGroupManager')->find();
        $storageGroupOptions = array();
        foreach ((array) $StorageGroups as $StorageGroup) {
            if (!$StorageGroup->isValid()) {
                continue;
            }
            $storageGroupOptions[$StorageGroup->get('id')] = $StorageGroup->get('name');
        }

        // Get next available port
        $nextPort = self::getClass('MulticastSnapinSessionManager')
            ->getNextAvailablePort();

        $fields = array(
            _('Session Name') => self::getClass('Process')
                ->input('name')
                ->placeholder(_('Enter session name'))
                ->required('required'),
            _('Snapin') => self::getClass('Process')
                ->select('snapinID', $snapinOptions)
                ->required('required'),
            _('Storage Group') => self::getClass('Process')
                ->select('storagegroupID', $storageGroupOptions)
                ->required('required'),
            _('Number of Clients') => self::getClass('Process')
                ->input('clients', '1')
                ->type('number')
                ->min('1')
                ->required('required'),
            _('Base Port') => self::getClass('Process')
                ->input('port', $nextPort)
                ->type('number')
                ->min('24576')
                ->max('65534')
                ->step('2')
                ->required('required')
                ->placeholder(_('Must be an even number')),
            _('Network Interface') => self::getClass('Process')
                ->input('interface', 'eth0')
                ->placeholder(_('e.g., eth0, ens160')),
            '&nbsp;' => self::getClass('Process')
                ->input('add', _('Create Session'))
                ->type('submit'),
        );

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
            $name = trim($_POST['name'] ?? '');
            $snapinID = (int) ($_POST['snapinID'] ?? 0);
            $storagegroupID = (int) ($_POST['storagegroupID'] ?? 0);
            $clients = (int) ($_POST['clients'] ?? 1);
            $port = (int) ($_POST['port'] ?? 0);
            $interface = trim($_POST['interface'] ?? 'eth0');

            if (empty($name)) {
                throw new Exception(_('Session name is required'));
            }

            if ($snapinID < 1) {
                throw new Exception(_('Please select a snapin'));
            }

            if ($storagegroupID < 1) {
                throw new Exception(_('Please select a storage group'));
            }

            if ($clients < 1) {
                throw new Exception(_('Client count must be at least 1'));
            }

            if ($port % 2 !== 0) {
                throw new Exception(_('Port must be an even number'));
            }

            if ($port < 24576 || $port > 65534) {
                throw new Exception(_('Port must be between 24576 and 65534'));
            }

            // Validate snapin exists
            $Snapin = self::getClass('Snapin', $snapinID);
            if (!$Snapin->isValid()) {
                throw new Exception(_('Invalid snapin selected'));
            }

            // Validate storage group exists
            $StorageGroup = self::getClass('StorageGroup', $storagegroupID);
            if (!$StorageGroup->isValid()) {
                throw new Exception(_('Invalid storage group selected'));
            }

            // Create session
            $Session = self::getClass('MulticastSnapinSession')
                ->set('name', $name)
                ->set('snapinID', $snapinID)
                ->set('storagegroupID', $storagegroupID)
                ->set('clients', $clients)
                ->set('sessclients', 0)
                ->set('port', $port)
                ->set('interface', $interface)
                ->set('stateID', 0) // Queued
                ->set('percent', 0)
                ->set('starttime', self::formatTime('now', 'Y-m-d H:i:s'));

            if (!$Session->save()) {
                throw new Exception(_('Failed to create session'));
            }

            self::$HookManager->processEvent(
                'MULTICASTSNAPIN_ADD',
                array('MulticastSnapinSession' => &$Session)
            );

            $this->setMessage(
                sprintf(
                    _('Session %s created successfully'),
                    $name
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
        $StorageGroup = $Session->getStorageGroup();
        $StorageNode = $Session->getStorageNode();

        $fields = array(
            _('Session ID') => $Session->get('id'),
            _('Session Name') => $Session->get('name'),
            _('Snapin') => $Snapin && $Snapin->isValid()
                ? $Snapin->get('name')
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
}
