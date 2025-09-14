<?php

namespace Lib\UserType;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserField\Types\BaseType;


class CUserTypeUserId extends BaseType
{
    const USER_TYPE_ID = 'userid';

    public static function getUserTypeDescription(): array
    {
        return [
            'USER_TYPE_ID' => self::USER_TYPE_ID,
            'CLASS_NAME' => __CLASS__,
            'DESCRIPTION' => 'Привязка к пользователю',
            'BASE_TYPE' => \CUserTypeManager::BASE_TYPE_INT,
        ];
    }

    public static function getDbColumnType(): string
    {
        global $DB;
        
        switch(strtolower($DB->type)) {
            case "mysql":
                return "int(18)";
            case "oracle":
                return "number(18)";
            case "mssql":
                return "int";
            default:
                return "int";
        }
    }

    public static function prepareSettings(array $userField): array
    {
        return [
            'DEFAULT_VALUE' => $userField['SETTINGS']['DEFAULT_VALUE'] ?? 0,
            'FILTERABLE' => $userField['SETTINGS']['FILTERABLE'] ?? 'N',
            'MANDATORY' => $userField['SETTINGS']['MANDATORY'] ?? 'N',
        ];
    }

    public static function getFilterHtml(array $userField, ?array $additionalParameters = []): string
    {
        $htmlControl = $additionalParameters ?? [];
        $value = $htmlControl['VALUE'] ?? '';
        $name = $htmlControl['NAME'] ?? '';
        
        return '<input type="text" name="'.htmlspecialcharsbx($name).'" value="'.htmlspecialcharsbx($value).'" size="10">';
    }

    public static function getAdminListViewHtml(array $userField, ?array $additionalParameters = []): string
    {
        $htmlControl = $additionalParameters ?? [];
        
        if (empty($htmlControl['VALUE'])) {
            return '';
        }

        $userId = intval($htmlControl['VALUE']);
        $user = \CUser::GetByID($userId)->Fetch();
        
        if ($user) {
            return $user['NAME'].' '.$user['LAST_NAME'].' ('.$user['EMAIL'].')';
        }
        
        return 'Пользователь не найден';
    }

    public static function getAdminListEditHtml(array $userField, ?array $additionalParameters = []): string
    {
        return static::getEditFormHtml($userField, $additionalParameters);
    }

    public static function getEditFormHtml(array $userField, ?array $additionalParameters = []): string
    {
        $htmlControl = $additionalParameters ?? [];
        $value = $htmlControl['VALUE'] ?? '';
        $fieldName = $htmlControl['NAME'] ?? $userField['FIELD_NAME'];
        
        ob_start();
        ?>
        <select name="<?= htmlspecialcharsbx($fieldName) ?>" style="width: 300px;">
            <option value="0"><?= 'Выберите пользователя' ?></option>
            <?php
            $rsUsers = \CUser::GetList(
                ($by = 'last_name'), 
                ($order = 'asc'),
                ['ACTIVE' => 'Y'],
                ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']]
            );
            
            while ($user = $rsUsers->Fetch()) {
                $selected = ($user['ID'] == $value) ? 'selected' : '';
                $userName = trim($user['NAME'].' '.$user['LAST_NAME']);
                $displayValue = $userName ? $userName.' ('.$user['EMAIL'].')' : $user['EMAIL'];
                ?>
                <option value="<?= $user['ID'] ?>" <?= $selected ?>>
                    <?= htmlspecialcharsbx($displayValue) ?>
                </option>
                <?php
            }
            ?>
        </select>
        <?php
        return ob_get_clean();
    }

    public static function checkFields(array $userField, $value): array
    {
        $aErrors = [];
        
        if ($value && !\CUser::GetByID($value)->Fetch()) {
            $aErrors[] = 'Указан несуществующий пользователь';
        }
        
        return $aErrors;
    }
}