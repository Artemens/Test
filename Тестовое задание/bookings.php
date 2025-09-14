<?
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin.php';

if (!$USER->IsAuthorized()) {
    $APPLICATION->AuthForm('Доступ запрещен');
}

$APPLICATION->SetTitle('Бронирование автомобилей');
?>

<? require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php'; ?>

<h1>Бронирование автомобилей</h1>
<div style="background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0;">
    <form method="GET" action="">
        <h3>Выберите время поездки:</h3>
        
        <div style="margin: 10px 0;">
            <strong>Начало:</strong>
            <input type="datetime-local" name="start_time" value="<?= htmlspecialchars($_GET['start_time'] ?? '') ?>" required>
        </div>
        
        <div style="margin: 10px 0;">
            <strong>Окончание:</strong>
            <input type="datetime-local" name="end_time" value="<?= htmlspecialchars($_GET['end_time'] ?? '') ?>" required>
        </div>
        
        <input type="submit" value="Показать доступные автомобили" style="padding: 10px 20px; background: #006dcc; color: white; border: none; border-radius: 3px; cursor: pointer;">
    </form>
</div>

<?
$componentFile = $_SERVER['DOCUMENT_ROOT'] . '/local/components/bitrix/car.availability.list/component.php';
if (file_exists($componentFile)) {
    require_once $componentFile;
    $component = new CarAvailabilityList();
    $component->initComponent('bitrix:car.availability.list');
    $component->executeComponent();
} else {
    echo '<div style="color: red;">Файл компонента не найден</div>';
}
?>

<? require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>