<?php

\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'Lib\UserType\CUserTypeUserId' => '/local/php_interface/lib/UserType/CUserTypeUserId.php',
]);

include_once __DIR__ . '/event_handler.php';

AddEventHandler('main', 'OnBuildGlobalMenu', function(&$aGlobalMenu, &$aModuleMenu) {
    $aModuleMenu[] = [
        'parent_menu' => 'global_menu_services',
        'sort' => 10,
        'url' => '/bitrix/admin/bookings.php',
        'text' => 'Бронирование авто',
        'title' => 'Бронирование служебных автомобилей'
    ];
});