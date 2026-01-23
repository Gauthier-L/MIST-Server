<?php
/**
 * Add Multicast Snapin API hook
 *
 * Adds multicast snapin classes to API
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Add Multicast Snapin API hook
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddMulticastSnapinAPI extends Hook
{
    /**
     * The name of the hook
     *
     * @var string
     */
    public $name = 'AddMulticastSnapinAPI';

    /**
     * The description of the hook
     *
     * @var string
     */
    public $description = 'Add multicast snapin classes to API';

    /**
     * Is the hook active
     *
     * @var bool
     */
    public $active = true;

    /**
     * The node for this hook
     *
     * @var string
     */
    public $node = 'multicastsnapin';

    /**
     * Initialize the hook
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        self::$HookManager
            ->register(
                'API_VALID_CLASSES',
                array($this, 'addApiClasses')
            );
    }

    /**
     * Add API classes
     *
     * @param mixed $arguments The arguments
     *
     * @return void
     */
    public function addApiClasses($arguments)
    {
        if (!in_array($this->node, (array) $_SESSION['PluginsInstalled'])) {
            return;
        }

        $arguments['validClasses'] = array_merge(
            (array) $arguments['validClasses'],
            array(
                'multicastsnapinsession',
                'multicastsnapinsessionassociation',
            )
        );
    }
}

$AddMulticastSnapinAPI = new AddMulticastSnapinAPI();
