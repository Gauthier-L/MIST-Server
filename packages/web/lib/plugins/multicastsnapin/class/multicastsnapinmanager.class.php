<?php
/**
 * MulticastSnapinManager service class
 *
 * Manages multicast snapin deployment sessions
 * Based on FOGMulticastManager pattern
 *
 * @category Service
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinManager service class
 *
 * @category Service
 * @package  FOGProject
 * @author   Gauthier-L, University of Lille, Campus-Gare RBX
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinManager extends FOGService
{
    /**
     * Service start
     *
     * @return void
     */
    public function serviceStart()
    {
        parent::serviceStart();
        $this->_commonOutput(
            sprintf(
                ' * Starting %s service',
                get_class($this)
            )
        );
    }

    /**
     * Main service loop (same pattern as FOGMulticastManager)
     *
     * @return void
     */
    public function serviceRun()
    {
        $this->_serviceLoop();
    }

    /**
     * Service loop implementation
     *
     * @return void
     */
    private function _serviceLoop()
    {
        $KnownTasks = [];
        $queueTasks = [];
        $first = true;

        while (true) {
            $completeTasks = $cancelTasks = [];

            // Sleep timer management
            $date = self::niceDate();
            if (!isset($nextrun)) {
                $nextrun = clone $date;
            }

            if ($date < $nextrun && $first === false) {
                usleep(100000); // 100ms
                continue;
            }

            // Wait for database
            $this->waitDbReady();

            // Reset next run time
            $sleepTime = (int) self::getSetting('MULTICASTSLEEPTIME') ?: 10;
            $nextrun = self::niceDate();
            $nextrun->modify(sprintf('+%d seconds', $sleepTime));

            // States
            $queuedStates = self::fastmerge(
                self::getQueuedStates(),
                (array) self::getProgressState()
            );

            try {
                // Check if multicast is globally enabled
                $mcEnabled = self::getSetting('MULTICASTGLOBALENABLED');
                if ($mcEnabled < 1) {
                    throw new Exception(' * Multicast service is globally disabled');
                }

                // Check each storage node
                foreach ($this->checkIfNodeMaster() as &$StorageNode) {
                    if (!$StorageNode->isValid()) {
                        continue;
                    }

                    // Get all multicast snapin sessions for this storage node
                    $MulticastSnapinTask = new MulticastSnapinTask();
                    $allTasks = $MulticastSnapinTask->getAllMulticastSnapinTasks(
                        $StorageNode->get('snapinpath'),
                        $StorageNode->get('id'),
                        $queuedStates
                    );

                    if (count($allTasks ?: []) < 1) {
                        self::outall(' * No new multicast snapin tasks found');
                        continue;
                    }

                    foreach ($allTasks as &$curTask) {
                        // Check available slots
                        $totalSlots = (int) self::getSetting('FOG_MULTICAST_MAX_SESSIONS') ?: 5;
                        $usedSlots = $StorageNode->getUsedSlotCount();
                        $groupOpenSlots = $totalSlots - $usedSlots;

                        $existing = self::_isMCTaskInList($KnownTasks, $curTask->getID());
                        $queued = self::_isMCTaskInList($queueTasks, $curTask->getID());

                        // NEW TASK
                        if (!$existing) {
                            // Check if we have available slots
                            if ($groupOpenSlots < 1) {
                                self::outall(
                                    sprintf(
                                        ' * No slots available (%d/%d used)',
                                        $usedSlots,
                                        $totalSlots
                                    )
                                );
                                $curTask->getSess()->set('stateID', self::getQueuedState());
                                $curTask->getSess()->save();
                                $queueTasks[] = $curTask;
                                continue;
                            }

                            // Validate task before starting
                            if (!file_exists($curTask->getSnapinPath())) {
                                self::outall(
                                    sprintf(
                                        ' * Snapin file not found: %s',
                                        $curTask->getSnapinPath()
                                    )
                                );
                                continue;
                            }

                            if (!$curTask->getClientCount()) {
                                self::outall(' * No clients included in task');
                                continue;
                            }

                            if (!is_numeric($curTask->getPortBase())
                                || !($curTask->getPortBase() % 2 == 0)
                            ) {
                                self::outall(' * Port must be even and numeric');
                                continue;
                            }

                            // START THE TASK
                            if (!$curTask->startTask()) {
                                self::outall(' * Failed to start multicast snapin task');
                                $curTask->killTask();
                                continue;
                            }

                            // Remove from queue if it was queued
                            if ($queued) {
                                $queueTasks = self::_removeFromKnownList(
                                    $queueTasks,
                                    $curTask->getID()
                                );
                            }

                            $KnownTasks[] = $curTask;

                            // Update state to in-progress
                            $Session = $curTask->getSess();
                            $Session->set('stateID', self::getProgressState());
                            $Session->save();

                            self::outall(
                                sprintf(
                                    ' * Started multicast snapin task ID %d (Port: %d, Clients: %d)',
                                    $curTask->getID(),
                                    $curTask->getPortBase(),
                                    $curTask->getClientCount()
                                )
                            );

                            continue;
                        }

                        // EXISTING TASK - Monitor
                        $jobcancelled = $jobcompleted = false;
                        $runningTask = self::_getMCExistingTask($KnownTasks, $curTask);

                        $Session = $runningTask->getSess();

                        $SessCancelled = $Session->get('stateID') == self::getCancelledState();
                        $SessCompleted = $Session->get('stateID') == self::getCompleteState();

                        if ($SessCancelled) {
                            $jobcancelled = true;
                        }

                        if ($SessCompleted || $runningTask->isSessionFinished()) {
                            $jobcompleted = true;
                        }

                        if (!$jobcancelled && !$jobcompleted) {
                            // Check if process is still running
                            if ($runningTask->isRunning($runningTask->procRef)) {
                                self::outall(
                                    sprintf(
                                        ' * Task ID %d is running with PID: %s',
                                        $runningTask->getID(),
                                        $runningTask->getPID($runningTask->procRef)
                                    )
                                );
                                $runningTask->updateStats();
                            } else {
                                self::outall(
                                    sprintf(
                                        ' * Task ID %d is no longer running',
                                        $runningTask->getID()
                                    )
                                );
                                $runningTask->killTask();

                                // Mark as complete if no error
                                if ($Session->get('clients') > 0) {
                                    $jobcompleted = true;
                                }
                            }
                        }

                        // Handle completed/cancelled tasks
                        if ($jobcompleted || $jobcancelled) {
                            if ($jobcompleted) {
                                $completeTasks[] = $runningTask;
                            }
                            if ($jobcancelled) {
                                $cancelTasks[] = $runningTask;
                            }

                            $runningTask->killTask();
                            $KnownTasks = self::_removeFromKnownList(
                                $KnownTasks,
                                $runningTask->getID()
                            );
                        }

                        unset($curTask);
                    }

                    unset($StorageNode);
                }

                // Finalize cancelled tasks
                foreach ($cancelTasks as &$Task) {
                    $Session = $Task->getSess();
                    $Session->cancel();
                    self::outall(
                        sprintf(
                            ' * Cancelled multicast snapin task ID %d',
                            $Task->getID()
                        )
                    );
                    unset($Task);
                }

                // Finalize completed tasks
                foreach ($completeTasks as &$Task) {
                    $Session = $Task->getSess();
                    $Session->complete();
                    self::outall(
                        sprintf(
                            ' * Completed multicast snapin task ID %d',
                            $Task->getID()
                        )
                    );
                    unset($Task);
                }
            } catch (Exception $e) {
                self::outall($e->getMessage());
            }

            if ($first) {
                $first = false;
            }
        }
    }

    /**
     * Check if task is in known list
     *
     * @param array $list   The list to check
     * @param int   $taskID The task ID
     *
     * @return bool True if in list
     */
    private static function _isMCTaskInList($list, $taskID)
    {
        foreach ((array) $list as &$task) {
            if ($task->getID() == $taskID) {
                return true;
            }
            unset($task);
        }
        return false;
    }

    /**
     * Get existing task from known list
     *
     * @param array  $list    The list to search
     * @param object $curTask The current task
     *
     * @return object|bool The task or false
     */
    private static function _getMCExistingTask($list, $curTask)
    {
        foreach ((array) $list as &$task) {
            if ($task->getID() == $curTask->getID()) {
                return $task;
            }
            unset($task);
        }
        return false;
    }

    /**
     * Remove task from known list
     *
     * @param array $list   The list
     * @param int   $taskID The task ID to remove
     *
     * @return array The updated list
     */
    private static function _removeFromKnownList($list, $taskID)
    {
        $newList = [];
        foreach ((array) $list as &$task) {
            if ($task->getID() != $taskID) {
                $newList[] = $task;
            }
            unset($task);
        }
        return $newList;
    }
}
