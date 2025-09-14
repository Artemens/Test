<?php

use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();
$eventManager->addEventHandler(
    'main',
    'OnUserTypeBuildList',
    ['Lib\UserType\CUserTypeUserId', 'getUserTypeDescription']
);