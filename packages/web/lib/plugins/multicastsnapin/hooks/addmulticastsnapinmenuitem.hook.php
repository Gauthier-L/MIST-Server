<?php
/**
 * Add Multicast Snapin menu item hook
 *
 * Adds menu item for multicast snapin management
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * Add Multicast Snapin menu item hook
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class AddMulticastSnapinMenuItem extends Hook
{
    /**
     * The name of the hook
     *
     * @var string
     */
    public $name = 'AddMulticastSnapinMenuItem';

    /**
     * The description of the hook
     *
     * @var string
     */
    public $description = 'Add multicast snapin management to menu';

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
                'MAIN_MENU_DATA',
                array($this, 'menuData')
            )
            ->register(
                'SEARCH_PAGES',
                array($this, 'addSearch')
            )
            ->register(
                'PAGES_WITH_OBJECTS',
                array($this, 'addPageWithObject')
            );
    }

    /**
     * Add menu item
     *
     * @param mixed $arguments The arguments
     *
     * @return void
     */
    public function menuData($arguments)
    {
        if (!in_array($this->node, (array) $_SESSION['PluginsInstalled'])) {
            return;
        }

        // Add menu item after snapins
        self::arrayInsertAfter(
            'snapin',
            $arguments['main'],
            $this->node,
            array(
                _('Multicast Snapin'),
                'fa fa-share-alt'
            )
        );
    }

    /**
     * Add search page
     *
     * @param mixed $arguments The arguments
     *
     * @return void
     */
    public function addSearch($arguments)
    {
        if (!in_array($this->node, (array) $_SESSION['PluginsInstalled'])) {
            return;
        }

        array_push(
            $arguments['searchPages'],
            $this->node
        );
    }

    /**
     * Add page with object
     *
     * @param mixed $arguments The arguments
     *
     * @return void
     */
    public function addPageWithObject($arguments)
    {
        if (!in_array($this->node, (array) $_SESSION['PluginsInstalled'])) {
            return;
        }

        array_push(
            $arguments['PagesWithObjects'],
            $this->node
        );
    }
}

$AddMulticastSnapinMenuItem = new AddMulticastSnapinMenuItem();
