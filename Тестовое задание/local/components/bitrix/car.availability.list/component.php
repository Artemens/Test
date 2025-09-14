<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class CarAvailabilityList extends CBitrixComponent
{
    const CARS_HL_ID = 12;
    const BOOKINGS_HL_ID = 13;
    const COMFORT_HL_ID = 9;
    const POSITIONS_HL_ID = 10;
    const DRIVERS_HL_ID = 11;
    const USER_POSITIONS_HL_ID = 14;

    public function executeComponent()
    {
        try {
            if (!CModule::IncludeModule('highloadblock')) {
                throw new Exception('Модуль Highload-блоков не установлен');
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'book_car') {
                $this->processBooking();
            }

            $startTime = $_GET['start_time'] ?? '';
            $endTime = $_GET['end_time'] ?? '';
            
            if ($startTime && $endTime) {
                $startTime = $this->convertToBitrixDateTime($startTime);
                $endTime = $this->convertToBitrixDateTime($endTime);
                $this->showAvailableCars($startTime, $endTime);
            }

        } catch (Exception $e) {
            echo '<div style="color: red; padding: 10px;">' . $e->getMessage() . '</div>';
        }
    }

    private function processBooking()
    {
        global $USER, $APPLICATION;
        
        if (!$USER->IsAuthorized()) {
            throw new Exception('Для бронирования необходимо авторизоваться');
        }

        $carId = (int)$_POST['car_id'];
        $startTime = $this->convertToBitrixDateTime($_POST['start_time']);
        $endTime = $this->convertToBitrixDateTime($_POST['end_time']);
        $bookedCarIds = $this->getBookedCarIds($startTime, $endTime);
        if (in_array($carId, $bookedCarIds)) {
            throw new Exception('Этот автомобиль уже забронирован на указанное время');
        }

        $this->createBooking([
            'CAR_ID' => $carId,
            'USER_ID' => $USER->GetID(),
            'START_TIME' => $startTime,
            'END_TIME' => $endTime,
            'STATUS' => 'confirmed'
        ]);
        LocalRedirect($APPLICATION->GetCurPageParam('', ['action', 'car_id', 'start_time', 'end_time']));
    }

    private function createBooking($data)
    {
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::BOOKINGS_HL_ID)->fetch();
        if (!$hlblock) {
            throw new Exception('HL-блок бронирований не найден');
        }
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $result = $entityClass::add([
            'UF_CAR_ID' => $data['CAR_ID'],
            'UF_EMPLOYEE_ID' => $data['USER_ID'], 
            'UF_DATE_START' => $data['START_TIME'],
            'UF_DATE_END' => $data['END_TIME'],
            'UF_STATUS' => $data['STATUS'],
            'UF_CREATED_AT' => new Bitrix\Main\Type\DateTime()
        ]);

        if (!$result->isSuccess()) {
            throw new Exception('Ошибка при создании брони: ' . implode(', ', $result->getErrorMessages()));
        }

        return $result->getId();
    }

    private function convertToBitrixDateTime($datetime)
    {
        if (strpos($datetime, 'T') !== false) {
            $datetime = str_replace('T', ' ', $datetime);
        }
        return new Bitrix\Main\Type\DateTime($datetime, 'Y-m-d H:i');
    }

    private function showAvailableCars($startTime, $endTime)
    {
        try {
            global $USER;
            $userId = $USER->GetID();
            
            if (!$userId) {
                throw new Exception('Пользователь не авторизован');
            }

            $positionId = $this->getUserPositionId($userId);
            if (!$positionId) {
                throw new Exception('Для вашей должности нет доступных автомобилей');
            }

            $comfortIds = $this->getAvailableComfortIds($positionId);
            if (empty($comfortIds)) {
                throw new Exception('Для вашей должности нет доступных категорий автомобилей');
            }

            $allCars = $this->getAllCarsByComfort($comfortIds);
            $bookedCarIds = $this->getBookedCarIds($startTime, $endTime);

            $availableCars = [];
            foreach ($allCars as $car) {
                if (!in_array($car['ID'], $bookedCarIds)) {
                    $carInfo = $this->getCarDetails($car['ID']);
                    if ($carInfo) {
                        $availableCars[] = $carInfo;
                    }
                }
            }

            if (!empty($availableCars)) {
                echo '<form method="POST" action="">';
                echo '<input type="hidden" name="action" value="book_car">';
                echo '<input type="hidden" name="start_time" value="' . htmlspecialchars($_GET['start_time']) . '">';
                echo '<input type="hidden" name="end_time" value="' . htmlspecialchars($_GET['end_time']) . '">';
                
                echo '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background: #f0f0f0;">';
                echo '<th>Модель</th>';
                echo '<th>Категория комфорта</th>';
                echo '<th>Водитель</th>';
                echo '<th>Действие</th>';
                echo '</tr>';
                
                foreach ($availableCars as $car) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($car['MODEL']) . '</td>';
                    echo '<td>' . htmlspecialchars($car['COMFORT']) . '</td>';
                    echo '<td>' . htmlspecialchars($car['DRIVER']) . '</td>';
                    echo '<td>';
                    echo '<button type="submit" name="car_id" value="' . $car['ID'] . '" 
                            style="padding: 5px 10px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;"
                            onclick="return confirm(\'Вы уверены, что хотите забронировать этот автомобиль?\')">
                            Забронировать
                         </button>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                echo '</form>';
            } else {
                echo '<p>Нет доступных автомобилей на указанный период</p>';
            }

        } catch (Exception $e) {
            echo '<div style="color: red; padding: 10px;">' . $e->getMessage() . '</div>';
        }
    }

    private function getUserPositionId($userId)
    {
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::USER_POSITIONS_HL_ID)->fetch();
        if (!$hlblock) return null;
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $result = $entityClass::getList([
            'select' => ['UF_POSITION_ID'],
            'filter' => ['UF_USER_ID' => $userId]
        ]);

        $row = $result->fetch();
        return $row ? $row['UF_POSITION_ID'] : null;
    }

    private function getAvailableComfortIds($positionId)
    {
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::POSITIONS_HL_ID)->fetch();
        if (!$hlblock) return [];
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $result = $entityClass::getList([
            'select' => ['UF_AVAILABLE_COMFORT'],
            'filter' => ['ID' => $positionId]
        ]);

        $row = $result->fetch();
        if (!$row) return [];

        $comfortIds = $row['UF_AVAILABLE_COMFORT'] ?? '';
        
        if (empty($comfortIds)) {
            return [];
        }

        if (is_string($comfortIds) && strpos($comfortIds, ',') !== false) {
            $resultIds = explode(',', $comfortIds);
            return array_filter(array_map('intval', $resultIds));
        }
        elseif (is_numeric($comfortIds)) {
            return [(int)$comfortIds];
        }
        elseif (is_array($comfortIds)) {
            return array_filter(array_map('intval', $comfortIds));
        }

        return [];
    }

    private function getAllCarsByComfort($comfortIds)
    {
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::CARS_HL_ID)->fetch();
        if (!$hlblock) return [];
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $result = $entityClass::getList([
            'select' => ['ID', 'UF_MODEL', 'UF_COMFORT_ID', 'UF_DRIVER_ID'],
            'filter' => ['UF_COMFORT_ID' => $comfortIds]
        ]);

        $cars = [];
        while ($row = $result->fetch()) {
            $cars[] = [
                'ID' => $row['ID'],
                'MODEL' => $row['UF_MODEL'],
                'COMFORT_ID' => $row['UF_COMFORT_ID'],
                'DRIVER_ID' => $row['UF_DRIVER_ID']
            ];
        }

        return $cars;
    }

    private function getBookedCarIds($startTime, $endTime)
    {
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::BOOKINGS_HL_ID)->fetch();
        if (!$hlblock) return [];
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $filter = [
            'LOGIC' => 'OR',
            [
                '>=UF_DATE_START' => $startTime,
                '<=UF_DATE_START' => $endTime,
            ],
            [
                '>=UF_DATE_END' => $startTime,
                '<=UF_DATE_END' => $endTime,
            ],
            [
                '<=UF_DATE_START' => $startTime,
                '>=UF_DATE_END' => $endTime,
            ]
        ];

        $result = $entityClass::getList([
            'select' => ['UF_CAR_ID'],
            'filter' => $filter
        ]);

        $bookedIds = [];
        while ($row = $result->fetch()) {
            $bookedIds[] = $row['UF_CAR_ID'];
        }

        return $bookedIds;
    }

    private function getCarDetails($carId)
    {
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::CARS_HL_ID)->fetch();
        if (!$hlblock) return null;
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $carData = $entityClass::getById($carId)->fetch();
        if (!$carData) return null;

        return [
            'ID' => $carData['ID'],
            'MODEL' => $carData['UF_MODEL'] ?? '',
            'COMFORT' => $this->getComfortName($carData['UF_COMFORT_ID']),
            'DRIVER' => $this->getDriverName($carData['UF_DRIVER_ID'])
        ];
    }

    private function getComfortName($comfortId)
    {
        if (!$comfortId) return '';
        
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::COMFORT_HL_ID)->fetch();
        if (!$hlblock) return '';
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $comfortData = $entityClass::getById($comfortId)->fetch();
        return $comfortData['UF_NAME'] ?? '';
    }

    private function getDriverName($driverId)
    {
        if (!$driverId) return '';
        
        $hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById(self::DRIVERS_HL_ID)->fetch();
        if (!$hlblock) return '';
        
        $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $driverData = $entityClass::getById($driverId)->fetch();
        return $driverData['UF_NAME'] ?? '';
    }
}
?>