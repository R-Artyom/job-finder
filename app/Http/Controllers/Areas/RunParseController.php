<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class RunParseController extends Controller
{
    // Данные о регионах для записи в базу
    private array $areas = [];

    // Данные о текущей стране при рекурсивной обработке данных
    private ?int $countryId = null;

    /**
     * Парсер дерева регионов
     */
    public function __invoke()
    {
        // Полное имя текущего контроллера с namespace
        $routeAction = get_class($this);

        // Блок для выброса исключений
        try {
            // Создание транзакции
            DB::beginTransaction();

            // * Дерево всех регионов
            $response = Http::retry(5, 2000) // 5 попыток c паузами 2 сек
                ->connectTimeout(10) // Ожидание соединения 10 сек
                ->timeout(90) // Общий timeout 90 сек
                ->get("https://api.hh.ru/areas");

            // Плохой ответ
            if (!$response->successful()) {
                throw new \Exception("Плохой ответ от API HH (код {$response->status()})");
            }
            $areasTreeData = $response->json();
            // Пустой ответ
            if (empty($areasTreeData)) {
                throw new \Exception("Пустой ответ от API HH");
            }

            // Сформировать массив регионов, учитывая все дочерние регионы
            $this->getAreas($areasTreeData);

            // Отсортировать по возрастанию сначала по parent_id, затем по id
            usort($this->areas, function($a, $b) {
                // Обрабатываем null значения - ставим их первыми
                if ($a['parent_id'] === null && $b['parent_id'] !== null) {
                    return -1;
                }
                if ($a['parent_id'] !== null && $b['parent_id'] === null) {
                    return 1;
                }
                // Если оба не null, сравниваем как числа
                if ($a['parent_id'] !== null && $b['parent_id'] !== null) {
                    $parentCompare = $a['parent_id'] <=> $b['parent_id'];
                    if ($parentCompare !== 0) {
                        return $parentCompare;
                    }
                }
                // Если parent_id одинаковые или оба null, сравниваем по id
                return $a['id'] <=> $b['id'];
            });

            // * Отсев регионов, в которых не изменились данные
            $currentAreasModels = Area::query()
                ->select('id', 'parent_id', 'country_id', 'name', 'utc_offset', 'created_at')
                ->get()
                ->keyBy('id');
            foreach ($this->areas as $key => $area) {
                $id = $area['id'];
                // Новую запись оставить
                if (!isset($currentAreasModels[$id])) {
                    continue;
                }
                // Существующую запись проверить на изменения
                $changed =
                    $currentAreasModels[$id]->parent_id !== $area['parent_id'] ||
                    $currentAreasModels[$id]->country_id !== $area['country_id'] ||
                    $currentAreasModels[$id]->name !== $area['name'] ||
                    $currentAreasModels[$id]->utc_offset !== $area['utc_offset'];
                // Если изменений нет, то обновлять данные региона не надо
                if (!$changed) {
                    unset($this->areas[$key]);
                }
            }

            // * Заполнение таблицы данными
            // Временное отключение проверки внешних ключей, т.к. при добавлении записи в БД бывают ссылки на ещё недобавленный регион
            Schema::disableForeignKeyConstraints();
            // Запись пакетами, чтобы не было слишком много строк одним запросом для MySQL
            foreach (array_chunk($this->areas, 100) as $chunk) {
                Area::query()->upsert(
                    $chunk,
                    ['id'], // Атрибут, по которому будет осуществляться поиск
                    ['parent_id', 'country_id', 'name', 'utc_offset', 'updated_at'] // Атрибуты, которые будут обновляться
                );
            }
            // Возврат проверки внешних ключей
            Schema::enableForeignKeyConstraints();

            // Фиксирование транзакции
            DB::commit();

        // Блок перехвата исключений
        } catch (ConnectionException $e) {
            // Откат транзакции
            DB::rollBack();
            // Логирование в файл
            logger()->error("🟡 Ошибка соединения при считывании регионов ({$routeAction}): " . $e->getMessage());
            // Уведомления
            $notification = ['🟡 Ошибка соединения при считывании регионов', $e->getMessage()];
            $this->sendEmailNotify($notification);
            $this->sendTelegramNotify($notification);
        } catch (\Exception $e) {
            // Откат транзакции
            DB::rollBack();
            // Логирование в файл
            logger()->error("🔴 Ошибка общая при считывании регионов ({$routeAction}): " . $e->getMessage());
            // Уведомления
            $notification = ['🔴 Ошибка общая при считывании регионов', $e->getMessage()];
            $this->sendEmailNotify($notification);
            $this->sendTelegramNotify($notification);
        }
    }

    /**
     * Рекурсивное считывание всех регионов
     *
     * @param array $areasTreeData
     * @return void
     */
    private function getAreas(array $areasTreeData): void
    {
        $currentDate = date('Y-m-d H:i:s');
        foreach ($areasTreeData as $area) {
            // Если текущий регион является страной (корень дерева)
            if (!isset($area['parent_id'])) {
                $this->countryId = (int) $area['id'];
            }

            $this->areas[] = [
                'id' => (int) $area['id'],
                'parent_id' => isset($area['parent_id']) ? (int) $area['parent_id'] : null ,
                'country_id' => $this->countryId,
                'name' => $area['name'],
                'utc_offset' => $area['utc_offset'] ?? null,
                'created_at' => $currentDate,
                'updated_at' => $currentDate,
            ];

            if (!empty($area['areas'])) {
                $this->getAreas($area['areas']);
            }
        }
    }
}
