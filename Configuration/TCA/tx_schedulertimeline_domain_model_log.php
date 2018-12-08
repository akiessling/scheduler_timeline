<?php

return [
    'ctrl' => [
        'label' => 'uid',
        'tstamp' => 'tstamp',
        'title' => 'LLL:EXT:scheduler_timeline/Resources/Private/Language/locallang_tca.xlf:tx_schedulertimeline_domain_model_log',
        'adminOnly' => 1,
        'rootLevel' => 1,
        'hideTable' => 1,
    ],
    'columns' => [
        'task' => [
            'label' => 'task',
            'config' => [
                'type' => 'input',
                'size' => '20',
                'max' => '30',
            ],
        ],
        'starttime' => [
            'label' => 'starttime',
            'config' => [
                'type' => 'input',
                'size' => '20',
                'max' => '30',
            ],
        ],
        'endtime' => [
            'label' => 'endtime',
            'config' => [
                'type' => 'input',
                'size' => '20',
                'max' => '30',
            ],
        ],
        'exception' => [
            'label' => 'exception',
            'config' => [
                'type' => 'input',
                'size' => '20',
                'max' => '30',
            ],
        ],
        'returnmessage' => [
            'label' => 'returnmessage',
            'config' => [
                'type' => 'input',
                'size' => '20',
                'max' => '30',
            ],
        ],
        'processid' => [
            'label' => 'processid',
            'config' => [
                'type' => 'input',
                'size' => '20',
                'max' => '30',
            ],
        ],


    ],
    'types' => [
        '0' => ['showitem' => 'task, starttime, endtime, exception, returnmessage, processid'],
    ],
];
