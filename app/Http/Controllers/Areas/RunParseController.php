<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class RunParseController extends Controller
{
    // Данные о регионах для записи в базу
    private array $areas = [];

    /**
     * Парсер дерева регионов
     */
    public function __invoke()
    {
        // Дерево всех регионов
        $response = Http::get("https://api.hh.ru/areas");
        if ($response->successful()) {
            $areasTreeData = $response->json();
            if (!empty($areasTreeData)) {
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
                    ->select('id', 'parent_id', 'name', 'utc_offset', 'created_at')
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
                        ['parent_id', 'name', 'utc_offset', 'updated_at'] // Атрибуты, которые будут обновляться
                    );
                }
                // Возврат проверки внешних ключей
                Schema::enableForeignKeyConstraints();
            }
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

            $this->areas[] = [
                'id' => (int) $area['id'],
                'parent_id' => isset($area['parent_id']) ? (int) $area['parent_id'] : null ,
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
