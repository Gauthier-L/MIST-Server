<?php
/**
 * Multicast Snapin Deployment Plugin Configuration
 *
 * This plugin enables multicast deployment of snapins to optimize
 * network bandwidth when deploying to multiple hosts simultaneously.
 *
 * @category Plugin
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$fog_plugin = array();
$fog_plugin['name'] = 'multicastsnapin';
$fog_plugin['description'] = 'Enables multicast deployment of snapins to multiple hosts simultaneously, optimizing network bandwidth usage for large-scale deployments.';
$fog_plugin['menuicon'] = 'fa fa-share-alt fa-fw';
$fog_plugin['menuicon_hover'] = null;
$fog_plugin['entrypoint'] = '';
