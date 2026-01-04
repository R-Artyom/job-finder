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
            $counter->save();

            // Начать отсчет времени
            $startTime = microtime(true);
            // Промежуточная отметка времени
            $fixedTime = microtime(true);

            // Повторять считывание вакансий в течение 55 сек, если счетчик не достиг предела
            while ($fixedTime - $startTime < 55 && $vacancyId < $counter->limit) {
                // Если ещё нет такой вакансии в базе MySql
                if (!Vacancy::query()->where('id', $vacancyId)->exists()) {
                    // Задержка от 30 мс до 70 мс - частые ошибки 403
                    // Задержка от 200 мс до 250 мс - частые ошибки 403
                    // Задержка от 300 мс до 350 мс
                    usleep(rand(300000, 350000));

                    // Блок для выброса исключений
                    try {
                        // Создание транзакции
                        DB::beginTransaction();

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
                                            // 404 - Работодатель отсутствует
                                            if ($response->status() === 404) {
                                                // Если в базе hh нет такого работодателя, то пустая запись, чтобы не нарушать ссылки на внешние ключи
                                                (new EmployersStoreController)(['id' => $employerId]);
                                            // 400 и остальные - Неправильный запрос и другое
                                            } else {
                                                $counter->status = 'error';
                                                throw new \Exception('Employer API error: ' . $response->body());
                                            }
                                        }
                                    }
                                }
                            } else {
                                $vacancyData['employer']['id'] = null;
                            }

                            // Запись данных о вакансии
                            (new StoreController)($vacancyData);
                        // 404 - Вакансия отсутствует, увеличиваем счетчик и идём дальше
                        } elseif ($response->status() !== 404) {
                            // 403 - Ошибка доступа к данным (частые запросы)
                            if ($response->status() === 403) {
                                throw new \Exception('Vacancy API error 403: ' . $response->body());
                            // 400 и остальные - Неправильный запрос и другое
                            } else {
                                $counter->status = 'error';
                                throw new \Exception('Vacancy API error 400: ' . $response->body());
                            }
                        }

                        // Инкремент счетчика
                        $vacancyId++;
                        $counter->value = $vacancyId;
                        $counter->save();

                        // Фиксирование транзакции
                        DB::commit();

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

                        // Достигнут предел счетчика
                        if ($vacancyId >= $counter->limit) {
                            $notifications[] = ['🟡 Отчёт', "Счетчик вакансий остановлен на значении $vacancyId"];
                            $counter->status = 'run';
                            $counter->save();
                        }
                    }
                // В базе MySql уже есть такая вакансия
                } else {
                    // Инкремент счетчика
                    $vacancyId++;
                    $counter->value = $vacancyId;
                    $counter->save();

                    // Каждые 100000 отчёт
                    if ($vacancyId % 100000 === 0) {
                        $notifications[] = ['🟢 Отчёт', "Счетчик вакансий достиг значения $vacancyId"];
                    }

                    // Достигнут предел счетчика
                    if ($vacancyId >= $counter->limit) {
                        $notifications[] = ['🟡 Отчёт', "Счетчик вакансий остановлен на значении $vacancyId"];
                        $counter->status = 'run';
                        $counter->save();
                    }
                }

                // Фиксировать отметку времени
                $fixedTime = microtime(true);
            }

            // Если скрипт выполнялся дольше минуты
            if ($fixedTime - $startTime > 60) {
                $scryptTime = $fixedTime - $startTime;
                // Логирование в файл
                logger()->error("Время выполнения скрипта $scryptTime сек " . '(' . route('vacancies.run') . ')');
                $notifications[] = ['⚪️ Отчёт', "Время выполнения скрипта $scryptTime сек", "vacancyId = $vacancyId"];
            }

            // Счетчик свободен, если не было ошибок
            if ($counter->status !== 'error') {
                $counter->status = 'run';
            }
            $counter->save();

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
