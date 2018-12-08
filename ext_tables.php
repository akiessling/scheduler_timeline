<?php
defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE' && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'AOE.SchedulerTimeline',
        'tools',
        'schedulertimeline',
        'after:txschedulerM1',
        [
            // An array holding the controller-action-combinations that are accessible
            'Timeline' => 'timeline',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:scheduler_timeline/Resources/Public/Images/moduleicon.png',
            'labels' => 'LLL:EXT:scheduler_timeline/Resources/Private/Language/locallang_mod.xlf'
        ]
    );
}
