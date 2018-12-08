<?php

namespace AOE\SchedulerTimeline\XClass;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Scheduler
 *
 * @package AOE\SchedulerTimeline\XClass
 */
class Scheduler extends \TYPO3\CMS\Scheduler\Scheduler
{

    const TABLE = 'tx_schedulertimeline_domain_model_log';

    /**
     * Wraps the executeTask method
     *
     * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task The task to execute
     * @return boolean Whether the task was saved successfully to the database or not
     * @throws \Exception
     */
    public function executeTask(\TYPO3\CMS\Scheduler\Task\AbstractTask $task)
    {
        $taskUid = $task->getTaskUid();

        // log start
        $logUid = $this->logStart($taskUid);

        $failure = null;
        try {
            $result = parent::executeTask($task);
        } catch (\Exception $e) {
            $failure = $e;
        }

        if ($result || $failure) {
            $returnMessage = '';
            if ($task instanceof \AOE\SchedulerTimeline\Interfaces\ReturnMessage || is_callable(array($task, 'getReturnMessage'))) {
                $returnMessage = $task->getReturnMessage();
            }

            // log end
            $this->logEnd($logUid, $failure, $returnMessage);
        } else {
            // task was not executed, because another task was running
            // and multiple execution is not allowed for this task
            $this->removeLog($logUid);
        }

        if ($failure instanceof \Exception) {
            throw $failure;
        }

        return $result;
    }

    /**
     * Extend the method to cleanup up the log table aswell
     *
     * @see tx_scheduler::cleanExecutionArrays()
     * @throws \Exception
     */
    protected function cleanExecutionArrays()
    {
        parent::cleanExecutionArrays();
        $this->cleanupLog();
    }

    /**
     * Cleanup log
     *
     * @return void
     */
    protected function cleanupLog()
    {
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['scheduler_timeline']);


        $table = 'tx_schedulertimeline_domain_model_log';
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete($table)
            ->where(
                $queryBuilder->expr()->gt('endtime', 0),
                $queryBuilder->expr()->lt('endtime', (time()- $extConf['cleanLogEntriesOlderThan'] * 60))
            )
            ->execute();


        // clean tasks, that exceeded the maxLifetime
        $maxDuration = $this->extConf['maxLifetime'] * 60;

        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->update($table)
            ->where(
                $queryBuilder->expr()->eq('endtime', 0),
                $queryBuilder->expr()->lt('starttime', (time() - $maxDuration))
            )
            ->set('endtime', time())
            ->set('exception', serialize(array('message' => 'Task was cleaned up, because it exceeded maxLifetime.')))
            ->execute();

        // check if process are still alive that have been started more than x minutes ago
        $checkProcessesAfter = intval($extConf['checkProcessesAfter']) * 60;
        if ($checkProcessesAfter) {

            $queryBuilder = $this->getQueryBuilder();

            $queryBuilder
                ->getRestrictions()
                ->removeAll();

            $res = $queryBuilder
                ->select('*')
                ->from($table, 't')
                ->where(
                    $queryBuilder->expr()->eq('t.endtime', $queryBuilder->createNamedParameter(0)),
                    $queryBuilder->expr()->lt('t.starttime', $queryBuilder->createNamedParameter((time() - $checkProcessesAfter)))
                )
                ->execute();

                while ($row = $res->fetch()) {
                    $processId = $row['processid'];
                    if (!$this->checkProcess($processId)) {

                        $queryBuilder = $this->getQueryBuilder();
                        $queryBuilder
                            ->update($table)
                            ->where(
                                $queryBuilder->expr()->eq('uid', $row['uid'])
                            )
                            ->set('endtime', time())
                            ->set('exception', serialize(array('message' => 'Task was cleaned up, because it seems to be dead.')))
                            ->execute();

                        $exception = new \TYPO3\CMS\Scheduler\FailedExecutionException('Task was cleaned up, because it seems to be dead.');

                        $queryBuilder = $this->getQueryBuilder();
                        $queryBuilder
                            ->update($table)
                            ->where(
                                $queryBuilder->expr()->eq('uid', $row['task'])
                            )
                            ->set('serialized_executions', '')
                            ->set('lastexecution_failure', serialize($exception))
                            ->execute();
                    }
                }
        }
    }

    /**
     * Log the start of a task
     *
     * @param int $taskUid
     * @throws \Exception
     * @return int
     */
    protected function logStart($taskUid)
    {
        $now = time();

        $db = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(static::TABLE);
        $db->insert(
            static::TABLE,
            [
                'pid' => '0',
                'tstamp' => $now,
                'starttime' => $now,
                'task' => $taskUid,
                'processid' => getmypid()
            ]
        );
        return $db->lastInsertId(static::TABLE);
    }

    /**
     * Remove a log entry
     *
     * @param int $logUid
     * @throws \Exception
     * @return void
     */
    protected function removeLog($logUid)
    {
        $table = 'tx_schedulertimeline_domain_model_log';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
        $connection->delete($table, ['uid' => $logUid], [$connection::PARAM_INT]);
    }

    /**
     * Log the end of a task
     *
     * @param int $logUid
     * @param \Exception $failure
     * @param string $returnMessage
     * @throws \Exception
     */
    protected function logEnd($logUid, $failure, $returnMessage)
    {
        $exception = '';
        if ($failure instanceof \Exception) { /* @var $failure \Exception */
            $exception = serialize(array(
                'message' => $failure->getMessage(),
                'stacktrace' => $failure->getTraceAsString(),
                'endtime' => time(),
                'class' => get_class($failure)
            ));
        }

        $db = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(static::TABLE);
        $db->update(
            static::TABLE,
            [
                'endtime' => time(),
                'exception' => $exception,
                'returnmessage' => $returnMessage
            ],
            [
                'uid' => $logUid
            ]
        );
    }

    /**
     * Check process
     *
     * @param int $pid
     * @return bool
     */
    protected function checkProcess($pid)
    {
        // form the filename to search for
        $file = '/proc/' . (int) $pid . '/cmdline';
        $fp = false;
        if (file_exists($file)) {
            $fp = @fopen($file, 'r');
        }

        if (!$fp) { // if file does not exist or cannot be opened, return false
            return false;
        }
        $buf = fgets($fp);
        fclose($fp);

        if ($buf === false) { // if we failed to read from file, return false
            return false;
        }
        return true;
    }

    /**
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder
     */
    private function getQueryBuilder()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(static::TABLE);
    }
}
