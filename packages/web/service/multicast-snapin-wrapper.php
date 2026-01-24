<?php
/**
 * Multicast Snapin Wrapper Download Service
 *
 * This service provides dynamically generated wrapper scripts for multicast snapin clients
 *
 * PHP version 5
 *
 * @category Service
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

require_once '../commons/base.inc.php';

// Get parameters
$taskID = isset($_REQUEST['taskid']) ? (int)$_REQUEST['taskid'] : null;
$mac = isset($_REQUEST['mac']) ? trim($_REQUEST['mac']) : null;

if (!$taskID) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('error' => 'Missing task ID'));
    exit;
}

try {
    // Get wrapper entry by task ID
    $WrapperEntry = FOGCore::getClass('MulticastSnapinWrapperEntryManager')
        ->getBySnapinTask($taskID);

    if (!$WrapperEntry) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(array('error' => 'Wrapper not found for task ID: ' . $taskID));
        exit;
    }

    // Verify host if MAC provided
    if ($mac) {
        $Host = $WrapperEntry->getHost();
        $hostMac = $Host->get('mac')->__toString();

        if (strcasecmp($hostMac, $mac) !== 0) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(array('error' => 'MAC address mismatch'));
            exit;
        }
    }

    // Get wrapper script content
    $script = $WrapperEntry->get('script');
    $osType = $WrapperEntry->get('ostype');

    // Determine file extension and content type
    if ($osType === 'windows') {
        $extension = 'ps1';
        $contentType = 'text/plain';
        $filename = sprintf('multicast-wrapper-%d.ps1', $taskID);
    } else {
        $extension = 'sh';
        $contentType = 'application/x-sh';
        $filename = sprintf('multicast-wrapper-%d.sh', $taskID);
    }

    // Send wrapper script
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($script));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo $script;

    // Log download
    FOGCore::getClass('MulticastSnapinManager')
        ->outall(
            sprintf(
                ' * Wrapper script downloaded for task %d (%s)',
                $taskID,
                $filename
            )
        );

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(
        array(
            'error' => 'Failed to retrieve wrapper',
            'message' => $e->getMessage()
        )
    );
    exit;
}
