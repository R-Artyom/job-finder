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
use Symfony\Component\DomCrawler\Crawler;

class RunHtmlParseController extends Controller
{
    // Данные для логирования
    private array $loggerContext = [];

    public function __invoke(string $counterName = 'vacancyId')
    {
        // Полное имя текущего контроллера с namespace
        $routeAction = get_class($this);

        // Получаем текущий счетчик или создаем его, если не существует
        $counter = Counter::query()->firstOrCreate(
            ['name' => $counterName],
            [
                'value' => 1,
                'limit' => 136057993,
                'status' => 'run'
            ],
        );
        $vacancyId = $counter->value;

        // * Обновление лимита счетчика вакансий
        if ($counter->status === 'run') {
            // Счетчик занят
            $counter->status = 'busy';
            $counter->save();
            // Параметры запроса вакансий
            $params = [
                'order_by' => 'publication_time',
            ];

            try {
                // Ограничение времени на установку соединения до 2 секунды и общего времени соединения до 3 секунд
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Cache-Control' => 'max-age=0',
                ])
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->get("https://hh.ru/search/vacancy", $params);
                if ($response->successful()) {
                    $html = $response->body();
                    $crawler = new Crawler($html);
                    $crawlerText = $crawler->text();

                    // * JSON объект массива "vacancies" (первый элемент)
                    $pos = strpos($crawlerText, '"vacancies"');
                    $vacancyJsonObject = $this->extractJsonObject($crawlerText, $pos);
                    $vacancy = json_decode($vacancyJsonObject, true);

                    // Новое значение счетчика
                    if (isset($vacancy['vacancyId']) && $counter->limit < $vacancy['vacancyId']) {
                        $counter->limit = $vacancy['vacancyId'];
                        $counter->save();
                    }
                } else {
                    // Обработка HTTP ошибок
                    logger()->warning('Counter API HTTP error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'url' => "https://hh.ru/search/vacancy?order_by=publication_time",
                    ]);
                }
            } catch (\Exception $e) {
                logger()->error('Counter API general error', [
                    'error' => $e->getMessage(),
                    'params' => $params,
                    'url' => "https://hh.ru/search/vacancy?order_by=publication_time",
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
                $vacancyIdCopy = $vacancyId;

                // Если ещё нет такой вакансии в базе MySql
                if (!Vacancy::query()->where('id', $vacancyId)->exists()) {
                    // Задержка от 30 мс до 70 мс - частые ошибки 403
                    // Задержка от 200 мс до 250 мс - частые ошибки 403
                    // Задержка от 300 мс до 350 мс - днём частые ошибки 403
                    // Задержка от 400 мс до 410 мс - норм
                    // Задержка от 340 мс до 350 мс - норм в выходные и праздники
                    // Задержка от 360 мс до 370 мс - норм для API
                    // Задержка от 370 мс до 400 мс
                    usleep(rand(370000, 400000));

                    // Блок для выброса исключений
                    try {
                        // Создание транзакции
                        DB::beginTransaction();

                        $response = Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                            'Accept-Encoding' => 'gzip, deflate, br',
                            'Connection' => 'keep-alive',
                            'Upgrade-Insecure-Requests' => '1',
                            'Cache-Control' => 'max-age=0',
                        ])
                            ->connectTimeout(2)
                            ->timeout(3)
                            ->get("https://hh.ru/vacancy/{$vacancyId}");

                        if ($response->successful() || $response->status() === 403) {
                            $vacancyData = $this->vacancyData($response);

                            // Если есть ссылка на работодателя
                            if (isset($vacancyData['employer']['id'])) {
                                $employerId = $vacancyData['employer']['id'];
                                // Если работодатель указан, то сначала надо записать данные о нём
                                if (!empty($employerId)) {
                                    // Если не существует такого работодателя в базе MySql
                                    if (!Employer::query()->where('id', $employerId)->exists()) {
                                        $response = Http::withHeaders([
                                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                                            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                                            'Accept-Encoding' => 'gzip, deflate, br',
                                            'Connection' => 'keep-alive',
                                            'Upgrade-Insecure-Requests' => '1',
                                            'Cache-Control' => 'max-age=0',
                                        ])
                                            ->connectTimeout(2)
                                            ->timeout(3)
                                            ->get("https://hh.ru/employer/{$employerId}");

                                        if ($response->successful()) {
                                            $data = $this->employerData($response);
                                            (new EmployersStoreController)($data);
                                        } else {
                                            // 404 - Работодатель отсутствует
                                            if ($response->status() === 404) {
                                                // Если в базе hh нет такого работодателя, то пустая запись, чтобы не нарушать ссылки на внешние ключи
                                                (new EmployersStoreController)(['id' => $employerId]);
                                            // 400 и остальные - Неправильный запрос и другое
                                            } else {
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

                        // Инкремент счетчика для следующей итерации
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
                        // Выход из цикла
                        break;
                    }

                // В базе MySql уже есть такая вакансия
                } else {
                    // Инкремент счетчика для следующей итерации
                    $vacancyId++;
                    $counter->value = $vacancyId;
                    $counter->save();
                }

                // Только если счётчик сдвинулся с места
                if ($vacancyIdCopy !== $vacancyId) {
                    // Каждые 100000 отчёт
                    if ($vacancyIdCopy % 100000 === 0) {
                        $notifications[] = ['🟢 Отчёт', "Счетчик вакансий достиг значения $vacancyIdCopy"];
                    }

                    // Достигнут предел счетчика
                    if ($vacancyIdCopy >= $counter->limit) {
                        $notifications[] = ['🟡 Отчёт', "Счетчик вакансий остановлен на значении $vacancyIdCopy"];
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

    /**
     * Извлечь JSON объект, начиная с указанной позиции
     *
     * @param string $text Текст, из которого необходимо извлечт объект
     * @param int|null $startPos Позиция начала
     * @return string|null
     */
    function extractJsonObject(string $text, ?int $startPos): ?string
    {
        // Количество скобок
        $depth = 0;
        $started = false;
        $endPos = $startPos;

        if (!$startPos) {
            return null;
        }

        for ($i = $startPos; $i < strlen($text); $i++) {
            $char = $text[$i];

            if ($char === '{') {
                if (!$started) {
                    // Нашли начало объекта
                    $started = true;
                    // Возвращаемся назад, чтобы захватить ключ "vacancyView"
                    $startPos = $i;
                }
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($started && $depth === 0) {
                    // Нашли конец объекта - глубина стала 0
                    $endPos = $i + 1;
                    break;
                }
            }
        }

        if ($started && $endPos > $startPos) {
            return substr($text, $startPos, $endPos - $startPos);
        }

        return null;
    }

    /**
     * Получить данные вакансии
     *
     * @param $response
     * @return array|null
     */
    function vacancyData($response): ?array
    {
        $html = $response->body();
        $crawler = new Crawler($html);
        $crawlerText = $crawler->text();

        // * JSON объект "vacancyView"
        $pos = strpos($crawlerText, '"vacancyView"');
        $vacancyViewJsonObject = $this->extractJsonObject($crawlerText, $pos);
        $vacancyView = json_decode($vacancyViewJsonObject, true);

        // * JSON объект "shortVacancy"
        $pos = strpos($crawlerText, '"shortVacancy"');
        $shortVacancyJsonObject = $this->extractJsonObject($crawlerText, $pos);
        $shortVacancy = json_decode($shortVacancyJsonObject, true);

        if ($vacancyView && $shortVacancy) {
            $vacancyData = [
                'id' => $vacancyView['vacancyId'],
                'name' => $vacancyView['name'],
                'area' => [
                    'id' => $vacancyView['area']['@id'] ?? null,
                ],
                'description' => $vacancyView['description'] ?? '',
                'employer' => [
                    'id' => $vacancyView['company']['id'] ?? null,
                ],
                'salary' => [
                    'from' => $vacancyView['compensation']['from'] ?? null,
                    'to' => $vacancyView['compensation']['to'] ?? null,
                    'currency' => $vacancyView['compensation']['currency'] ?? null,
                ],
                'archived' => isset($vacancyView['status']['archived']) ? 1 : null,
                'published_at' => $vacancyView['publicationDate'] ?? null,
                'initial_created_at' => $shortVacancy['creationTime'] ?? null,
            ];
            return $vacancyData;
        // Если есть только укророченные данные (нет дорступа, ош. 403)
        } elseif ($shortVacancy) {
            $vacancyData = [
                'id' => $shortVacancy['vacancyId'],
                'name' => $shortVacancy['name'],
                'area' => [
                    'id' => $shortVacancy['area']['@id'] ?? null,
                ],
//                'description' => $vacancyView['description'] ?? '',
                'employer' => [
                    'id' => $shortVacancy['company']['id'] ?? null,
                ],
//                'salary' => [
//                    'from' => $vacancyView['compensation']['from'] ?? null,
//                    'to' => $vacancyView['compensation']['to'] ?? null,
//                    'currency' => $vacancyView['compensation']['currency'] ?? null,
//                ],
                'archived' => false,
                'published_at' => $shortVacancy['publicationTime']['$'] ?? null,
                'initial_created_at' => $shortVacancy['creationTime'] ?? null,
            ];
            return $vacancyData;
        }

        return null;
    }

    /**
     * Получить данные работодателя
     *
     * @param $response
     * @return array|null
     */
    function employerData($response): ?array
    {
        $html = $response->body();
        $crawler = new Crawler($html);
        $crawlerText = $crawler->text();

        // * JSON объект "employerInfo"
        $pos = strpos($crawlerText, '"employerInfo"');
        $employerInfoJsonObject = $this->extractJsonObject($crawlerText, $pos);
        $employerInfo = json_decode($employerInfoJsonObject, true);

        // * JSON объект "employerOrganizationSchema"
        $pos = strpos($crawlerText, '"employerOrganizationSchema"');
        $employerOrganizationSchemaJsonObject = $this->extractJsonObject($crawlerText, $pos);
        $employerOrganizationSchema = json_decode($employerOrganizationSchemaJsonObject, true);

        // * JSON объект "employerLogo"
        $pos = strpos($crawlerText, '"employerLogo"');
        $employerLogoJsonObject = $this->extractJsonObject($crawlerText, $pos);
        $employerLogo = json_decode($employerLogoJsonObject, true);

        if ($employerInfo && $employerOrganizationSchema && $employerLogo) {
            // Сохраняем данные
            $employerData = [
                'id' => $employerInfo['id'],
                'name' => $employerInfo['name'],
                'area' => [
                    'id' => isset($employerInfo['address']) ? DB::table('areas')->where('name', $employerInfo['address'])->value('id') : null,
                ],
                'site_url' => $employerOrganizationSchema['siteUrl'] ?? null,
                'logo_urls' => [
                    '240' => $employerOrganizationSchema['logoUrl'] ?? null,
                ],
                'created_at' => null,
            ];
            return $employerData;
        }

        return null;
    }
}
