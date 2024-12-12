<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employers\StoreController as EmployersStoreController;
use App\Models\Counter;
use App\Models\Employer;
use App\Models\Vacancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RunParseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        // Получаем текущий счетчик или создаем его, если не существует
        $counter = Counter::query()->firstOrCreate(
            ['name' => 'vacancyId'],
            ['value' => 1, 'status' => 'run']
        );
        $vacancyId = $counter->value;

        if ($counter->status === 'run') {
            // Блок для выброса исключений
            try {
                // Создание транзакции
                DB::beginTransaction();

                if ($vacancyId < $counter->limit) {
                    // Если ещё нет такой вакансии
                    if (!Vacancy::query()->where('id', $vacancyId)->exists()) {
                        // Запрос данных о вакансии
                        $response = Http::get("https://api.hh.ru/vacancies/$vacancyId");
                        if ($response->successful()) {
                            $vacancyData = $response->json();
                            // Если работодатель указан, то сначала надо записать данные о нём
                            if (!empty($vacancyData['employer']['id'])) {
                                // Если не существует такого работодателя
                                if (!Employer::query()->where('id', $vacancyData['employer']['id'])->exists()) {
                                    $response = Http::get("https://api.hh.ru/employers/{$vacancyData['employer']['id']}");
                                    $data = $response->json();
                                    if ($response->successful()) {
                                        (new EmployersStoreController)($data);
                                    } else {
                                        // Если в базе hh нет такого работодателя, то пустая запись
                                        (new EmployersStoreController)(['id' => $vacancyData['employer']['id']]);
                                    }
                                }
                            }
                            // Запись данных о вакансии
                            (new StoreController)($vacancyData);
                        }
                    }
                    // Инкремент счетчика с сохранением в базе
                    $counter->increment('value');
                }

                // Фиксирование транзакции
                DB::commit();

                // Блок перехвата исключений
            } catch (\Exception $e) {
                // Откат транзакции
                DB::rollBack();
                $counter->update(['status' => 'error']);
                // Логирование в файл
                logger(route('vacancies.run'),
                    [
                        'vacancyId' => $vacancyId,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }
}
