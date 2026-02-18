<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employers\StoreController as EmployersStoreController;
use App\Models\Counter;
use App\Models\Employer;
use App\Models\Vacancy;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RunParseController extends Controller
{
    // Данные для логирования
    private array $loggerContext = [];

    public function __invoke()
    {
        // Полное имя текущего контроллера с namespace
        $routeAction = get_class($this);

        // Получаем текущий счетчик или создаем его, если не существует
        $counter = Counter::query()->firstOrCreate(
            ['name' => 'vacancyId'],
            ['value' => 1, 'status' => 'run']
        );
        $vacancyId = $counter->value;

        // * Обновление лимита счетчика вакансий
        if ($counter->status === 'run') {
            // Счетчик занят
            $counter->status = 'busy';
            $counter->save();
            // Параметры запроса вакансий
            $params = [
                'page' => 0,
                // Дата за минуту до текущей в формате <2026-01-10T02:59:00> по МСК
                'date_from' => Carbon::now('Europe/Moscow')->subMinute()->format('Y-m-d\TH:i:s'),
                // Текущая дата в формате <2026-01-10T03:00:00> по МСК
                'date_to' => Carbon::now('Europe/Moscow')->format('Y-m-d\TH:i:s'),
                'order_by' => 'publication_time',
                'per_page' => 1,
            ];

            try {
                // Ограничение времени на установку соединения до 1 секунды и общего времени соединения до 2 секунд
                $response = Http::connectTimeout(1)
                    ->timeout(2)
                    ->get('https://api.hh.ru/vacancies', $params);
                if ($response->successful()) {
                    $data = $response->json();
                    // Новое значение счетчика
                    if (!empty($data['items'][0]['id']) && $counter->limit < $data['items'][0]['id']) {
                        $counter->limit = $data['items'][0]['id'];
                        $counter->save();
                    }
                } else {
                    // Обработка HTTP ошибок
                    logger()->warning('Counter API HTTP error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'url' => "https://api.hh.ru/vacancies?&page=0&date_from={$params['date_from']}&date_to={$params['date_to']}&order_by=publication_time&per_page=1",
                    ]);
                }
            } catch (\Exception $e) {
                logger()->error('Counter API general error', [
                    'error' => $e->getMessage(),
                    'params' => $params,
                    'url' => "https://api.hh.ru/vacancies?&page=0&date_from={$params['date_from']}&date_to={$params['date_to']}&order_by=publication_time&per_page=1",
                ]);
            }
            // Счетчик свободен
            $counter->status = 'run';
            $counter->save();
        }

        // * Считывание вакансий
        if ($counter->status === 'run' && $vacancyId <= $counter->limit) {
            // Счетчик занят
            $counter->status = 'busy';
            $counter->save();

            // Начать отсчет времени
            $startTime = microtime(true);
            // Промежуточная отметка времени
            $fixedTime = microtime(true);

            // Повторять считывание вакансий в течение 55 сек, если счетчик не достиг предела
            while ($fixedTime - $startTime < 55 && $vacancyId <= $counter->limit) {
                // Если ещё нет такой вакансии в базе MySql
                if (!Vacancy::query()->where('id', $vacancyId)->exists()) {
                    // Задержка от 30 мс до 70 мс - частые ошибки 403
                    // Задержка от 200 мс до 250 мс - частые ошибки 403
                    // Задержка от 300 мс до 350 мс - днём частые ошибки 403
                    // Задержка от 400 мс до 410 мс - норм
                    // Задержка от 340 мс до 350 мс - норм в выходные и праздники
                    // Задержка от 360 мс до 370 мс
                    usleep(rand(360000, 370000));

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
                                        if ($response->successful()) {
                                            $data = $response->json();
                                            (new EmployersStoreController)($data);
                                        } else {
                                            // 404 - Работодатель отсутствует
                                            if ($response->status() === 404) {
                                                // Если в базе hh нет такого работодателя, то пустая запись, чтобы не нарушать ссылки на внешние ключи
                                                (new EmployersStoreController)(['id' => $employerId]);
                                            // 400 и остальные - Неправильный запрос и другое
                                            } else {
                                                $counter->status = 'error';
                                                // Контекст для лога
                                                $this->loggerContext = [
                                                    'vacancyId' => $vacancyId,
                                                    'interval' => microtime(true) - $fixedTime,
                                                    'status' => $response->status(),
                                                    'response' => $response->body(),
                                                ];
                                                throw new \Exception('Employer API error ' . $response->status());
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
                            // Контекст для лога
                            $this->loggerContext = [
                                'vacancyId' => $vacancyId,
                                'interval' => microtime(true) - $fixedTime,
                                'status' => $response->status(),
                                'response' => $response->body(),
                            ];
                            // 403 - Ошибка доступа к данным (частые запросы)
                            // 502 - Сервер перегружен из-за высокого трафика
                            if ($response->status() !== 403 && $response->status() !== 502) {
                                $counter->status = 'error';
                            }
                            throw new \Exception('Vacancy API error ' . $response->status());
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
                        // Откат/Выравнивание локального счетчика
                        $vacancyId = $counter->value;
                        // Логирование в файл
                        logger()->error('🟡 Ошибка соединения ' . $routeAction,
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
                        logger()->error('🔴 Ошибка общая ' . $routeAction . ' ' . $e->getMessage(), $this->loggerContext);
                        $this->loggerContext = [];
                        // Уведомление
                        $notifications[] = ['🔴 Ошибка общая', $e->getMessage()];
                        // Фиксировать отметку времени
                        $fixedTime = microtime(true);
                        // Выход из цикла, при этом finally всё равно выполнится
                        break;

                    } finally {
                        // Каждые 100000 отчёт
                        if ($vacancyId % 100000 === 0) {
                            $notifications[] = ['🟢 Отчёт', "Счетчик вакансий достиг значения $vacancyId"];
                        }

                        // Достигнут предел счетчика
                        if ($vacancyId >= $counter->limit) {
                            $notifications[] = ['🟡 Отчёт', "Счетчик вакансий остановлен на значении $vacancyId"];
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
                        $notifications[] = ['🟢 Отчёт', "Счетчик вакансий остановлен на значении $vacancyId"];
                    }
                }

                // Фиксировать отметку времени
                $fixedTime = microtime(true);
            }

            // Если скрипт выполнялся дольше минуты
            if ($fixedTime - $startTime > 60) {
                $scryptTime = $fixedTime - $startTime;
                // Логирование в файл
                logger()->error("Время выполнения скрипта $scryptTime сек " . $routeAction);
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
