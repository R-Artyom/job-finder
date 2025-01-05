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

        if ($counter->status === 'run' && $vacancyId < $counter->limit) {
            // Счетчик занят
            $counter->status = 'busy';
            $counter->update(['value' => $vacancyId]);

            // Начать отсчет времени
            $startTime = microtime(true);
            // Промежуточная отметка времени
            $fixedTime = microtime(true);

            // Повторять считывание вакансий в течение 57 сек
            while ($fixedTime - $startTime < 57) {
                // Задержка от 10 мс до 100 мс
                usleep(rand(10000, 100000));

                // Блок для выброса исключений
                try {
                    // Создание транзакции
                    DB::beginTransaction();

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

                    // Фиксирование транзакции
                    if ($vacancyId < $counter->limit) {
                        DB::commit();
                    // Достигнут предел счетчика
                    } else {
                        // Счетчик свободен
                        $counter->status = 'run';
                        $counter->update(['value' => $vacancyId]);
                        DB::commit();
                        return;
                    }

                // Блок перехвата исключений
                } catch (ConnectionException $e) {
                    // Откат транзакции
                    DB::rollBack();
                    logger()->error('🟡 Ошибка соединения ' . '(' . route('vacancies.run') . ')',
                        [
                            'vacancyId' => $vacancyId,
                            'message' => $e->getMessage()
                        ]
                    );
                    $notifications[] = ['🟡 Ошибка соединения', $e->getMessage()];
                } catch (\Exception $e) {
                    // Откат транзакции
                    DB::rollBack();
                    $counter->update(['status' => 'error']);
                    // Логирование в файл
                    logger()->error('🔴 Ошибка общая ' . '(' . route('vacancies.run') . ')',
                        [
                            'vacancyId' => $vacancyId,
                            'error' => $e->getMessage(),
                        ]
                    );
                    $notifications[] = ['🔴 Ошибка общая', $e->getMessage()];
                    break;

                } finally {
                    // Каждые 100000 отчёт
                    if ($vacancyId % 100000 === 0) {
                        $notifications[] = ['🟢 Отчёт', "Счетчик вакансий достиг значения $vacancyId"];
                    }
                }

                // Фиксировать отметку времени
                $fixedTime = microtime(true);
            }
            // Если скрипт выполнялся дольше минуты
            if ($fixedTime - $startTime > 60) {
                // Логирование в файл
                logger()->error('Время выполнения скрипта > 60 сек ' . '(' . route('vacancies.run') . ')');
                $notifications[] = ['⚪️ Отчёт', 'Время выполнения скрипта более 60 секунд'];
            }

            // Счетчик свободен, если не было ошибок
            if ($counter->status !== 'error') {
                $counter->status = 'run';
                $counter->update(['value' => $vacancyId]);
            }

            // Отправка уведомлений
            if (isset($notifications)) {
                foreach ($notifications as $notification) {
                    $this->sendEmailNotify($notification);
                    $this->sendTelegramNotify($notification);
                }
            }
        }
    }
}
