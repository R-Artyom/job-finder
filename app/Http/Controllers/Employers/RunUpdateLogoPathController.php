<?php

namespace App\Http\Controllers\Employers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Employer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class RunUpdateLogoPathController extends Controller
{
    // Данные для логирования
    private array $loggerContext = [];

    public function __invoke()
    {
        // Полное имя текущего контроллера с namespace
        $routeAction = get_class($this);

        // Получаем текущий счетчик или создаем его, если не существует
        $counter = Counter::query()->firstOrCreate(
            ['name' => 'employerId'],
            [
                'value' => 1,
                'limit' => 12545879,
                'status' => 'run'
            ]
        );
        $employerId = $counter->value;

        // * Обновление данных у работодателей
        if ($counter->status === 'run' && $employerId < $counter->limit) {
            // Счетчик занят
            $counter->status = 'busy';
            $counter->save();

            // Начать отсчет времени
            $startTime = microtime(true);
            // Промежуточная отметка времени
            $fixedTime = microtime(true);

            // Счетчик ошибок соединения
            $connectionExceptionCounter = 0;

            // Повторять считывание работодателей в течение 55 сек, если счетчик не достиг предела
            while ($fixedTime - $startTime < 55 && $employerId < $counter->limit) {
                $employerIdCopy = $employerId;

                $employer = Employer::query()
                    ->where('id', $employerId)
                    ->first();

                // Если работодатель есть в базе, но лого нет, то делаем паузу и запрос нового лого
                if ($employer && !isset($employer->logo_path)) {
                    // Задержка от 30 мс до 70 мс - частые ошибки 403
                    // Задержка от 200 мс до 250 мс - частые ошибки 403
                    // Задержка от 300 мс до 350 мс - днём частые ошибки 403
                    // Задержка от 400 мс до 410 мс - норм
                    // Задержка от 340 мс до 350 мс - норм в выходные и праздники
                    // Задержка от 360 мс до 370 мс
                    usleep(rand(360000, 370000));

                    // Блок для выброса исключений
                    try {
                        // Признак транзакции
                        $inTransaction = false;
                        // Запрос данных о работодателе с ограничением времени на установку соединения до 4 секунд и общего времени соединения до 5 секунд
                        $response = Http::connectTimeout(4)
                            ->timeout(5)
                            ->get("https://api.hh.ru/employers/{$employerId}");

                        // Создание транзакции после Http запроса
                        DB::beginTransaction();
                        // Признак транзакции
                        $inTransaction = true;

                        // Если запрос был удачным
                        if ($response->successful()) {
                            $data = $response->json();
                            // Id логотипа "240"
                            if (!empty($data['logo_urls']['240'])) {
                                $employer->logo_path = (new StoreController)->extractLogoPath($data['logo_urls']['240']);
                            }
                            // Если есть данные о логотипе
                            if ($employer->logo_path) {
                                // Сохранить обновленные данные
                                $employer->save();
                            }
                        // Если запрос был НЕудачным
                        } else {
                            // 404 - Работодатель отсутствует, поэтому повторный запрос не нужен
                            // !404 - Работодатель скорее всего есть, нужен повторный запрос
                            if ($response->status() !== 404) {
                                $counter->status = 'error';
                                // Контекст для лога
                                $this->loggerContext = [
                                    'employerId' => $employerId,
                                    'interval' => microtime(true) - $fixedTime,
                                    'status' => $response->status(),
                                    'response' => $response->body(),
                                ];
                                throw new \Exception('Employer API error ' . $response->status());
                            }
                        }

                        // Инкремент счетчика
                        $employerId++;
                        $counter->value = $employerId;
                        $counter->save();

                        // Фиксирование транзакции
                        DB::commit();

                    // Блок перехвата исключений
                    } catch (ConnectionException $e) {
                        // Откат транзакции
                        if ($inTransaction) {
                            DB::rollBack();
                        }
                        // Необходимо повторить запрос
                        $employerId = $employerIdCopy;
                        // Логирование в файл
                        logger()->error('🟡 Ошибка соединения ' . $routeAction,
                            [
                                'employerId' => $employerId,
                                'message' => $e->getMessage()
                            ]
                        );
                        $notifications[] = ['🟡 Ошибка соединения', $e->getMessage()];

                        $connectionExceptionCounter++;
                        // Остановка считываний в случае отсутствия связи
                        if ($connectionExceptionCounter > 30) {
                            $counter->status = 'error';
                            // Фиксировать отметку времени
                            $fixedTime = microtime(true);
                            // Выход из цикла, при этом finally всё равно выполнится
                            break;
                        }
                    } catch (\Exception $e) {
                        // Откат транзакции
                        if ($inTransaction) {
                            DB::rollBack();
                        }
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
                        if ($employerId % 100000 === 0) {
                            $notifications[] = ['🟢 Отчёт', "Счетчик работодателей достиг значения $employerId"];
                        }

                        // Достигнут предел счетчика
                        if ($employerId >= $counter->limit) {
                            $notifications[] = ['🟢 Отчёт', "Счетчик работодателей остановлен на значении $employerId"];
                        }
                    }
                // Если лого работодателя уже есть в базе, то оставляем как есть и переходим к следующему id работодателя
                } else {
                    // Инкремент счетчика
                    $employerId++;
                    $counter->value = $employerId;
                    $counter->save();

                    // Каждые 100000 отчёт
                    if ($employerId % 100000 === 0) {
                        $notifications[] = ['🟢 Отчёт', "Счетчик работодателей достиг значения $employerId"];
                    }

                    // Достигнут предел счетчика
                    if ($employerId >= $counter->limit) {
                        $notifications[] = ['🟢 Отчёт', "Счетчик работодателей остановлен на значении $employerId"];
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
                $notifications[] = ['⚪️ Отчёт', "Время выполнения скрипта $scryptTime сек", "vacancyId = $employerId"];
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
