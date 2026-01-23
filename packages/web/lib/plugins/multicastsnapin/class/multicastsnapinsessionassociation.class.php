<?php
/**
 * MulticastSnapinSessionAssociation class
 *
 * Association between multicast sessions and hosts
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinSessionAssociation class
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinSessionAssociation extends FOGController
{
    /**
     * The database table name
     *
     * @var string
     */
    protected $databaseTable = 'multicastSnapinSessionsAssoc';

    /**
     * The database field mappings
     *
     * @var array
     */
    protected $databaseFields = array(
        'id' => 'mssaID',
        'msID' => 'mssID',
        'hostID' => 'mssaHostID',
    );

    /**
     * Required database fields
     *
     * @var array
     */
    protected $databaseFieldsRequired = array(
        'msID',
        'hostID',
    );

    /**
     * Additional fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'session',
        'host',
    );

    /**
     * Database field to class relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'MulticastSnapinSession' => array(
            'id',
            'msID',
            'session',
        ),
        'Host' => array(
            'id',
            'hostID',
            'host',
        ),
    );
}
