<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employers\StoreController as EmployersStoreController;
use App\Models\Counter;
use App\Models\Employer;
use App\Models\Vacancy;
use Illuminate\Http\Client\ConnectionException;
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
            // Счетчик занят
            $counter->status = 'busy';
            $counter->update(['value' => $vacancyId]);

            // Блок для выброса исключений
            try {
                // Создание транзакции
                DB::beginTransaction();

                if ($vacancyId < $counter->limit) {
                    // Если ещё нет такой вакансии в базе MySql
                    if (!Vacancy::query()->where('id', $vacancyId)->exists()) {
                        // Запрос данных о вакансии
                        $response = Http::get("https://api.hh.ru/vacancies/$vacancyId");
                        if ($response->successful()) {
                            $vacancyData = $response->json();
                            // Если есть ссылка на работодателя
                            if (isset($vacancyData['employer']['id'])) {
                                $employerId = $vacancyData['employer']['id'];
                                // Если работодатель указан, то сначала надо записать данные о нём
                                if (!empty($employerId)) {
                                    // Если не существует такого работодателя в базе MySql
                                    if (!Employer::query()->where('id', $employerId)->exists()) {
                                        $response = Http::get("https://api.hh.ru/employers/{$employerId}");
                                        $data = $response->json();
                                        if ($response->successful()) {
                                            (new EmployersStoreController)($data);
                                        } else {
                                            // Если в базе hh нет такого работодателя, то пустая запись
                                            (new EmployersStoreController)(['id' => $employerId]);
                                        }
                                    }
                                }
                            } else {
                                $vacancyData['employer']['id'] = null;
                            }

                            // Запись данных о вакансии
                            (new StoreController)($vacancyData);
                        }
                    }
                    // Инкремент счетчика с сохранением в базе
                    $vacancyId++;
                    $counter->update(['value' => $vacancyId]);
                }

                // Счетчик свободен
                $counter->status = 'run';
                $counter->update(['value' => $vacancyId]);

                // Фиксирование транзакции
                DB::commit();

            // Блок перехвата исключений
            } catch (ConnectionException $e) {
                // Откат транзакции
                DB::rollBack();
                // Счетчик свободен
                $counter->status = 'run';
                $counter->update(['value' => $vacancyId]);
                logger()->error('Ошибка соединения ' . '(' . route('vacancies.run') . ')',
                    [
                        'vacancyId' => $vacancyId,
                        'message' => $e->getMessage()
                    ]
                );
            // Общая ошибка
            } catch (\Exception $e) {
                // Откат транзакции
                DB::rollBack();
                $counter->update(['status' => 'error']);
                // Логирование в файл
                logger()->error('Ошибка общая ' . '(' . route('vacancies.run') . ')',
                    [
                        'vacancyId' => $vacancyId,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }
}
