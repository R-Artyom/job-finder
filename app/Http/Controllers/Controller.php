<?php

namespace App\Http\Controllers;

use App\Mail\Notify;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Telegram\Bot\Laravel\Facades\Telegram;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

// ****************************************************************************
//                                 Уведомления
// ****************************************************************************

    /**
     * Отправка уведомления на почту
     *
     * @param array $notifyData тема письма (первый элемент массива) + текст письма (всё остальное, каждый элемент - новая строка)
     * @param string|null $email
     * @return void
     */
    public function sendEmailNotify(array $notifyData, string $email = null): void
    {
        // Отправка уведомлений, если разрешено
        if (config('enable.emailNotifications') === true) {
            // * Сборка письма
            // Тема
            $data['subject'] = empty($notifyData) ? 'Тема письма отсутствует' : $notifyData[array_key_first($notifyData)];
            unset($notifyData[0]);
            // Тело
            $data['message'] = empty($notifyData) ? 'Тело письма отсутствует' : implode('<br>', $notifyData);

            // * Отправка письма
            if (isset($email)) {
                Mail::to($email)->send(new Notify($data));
            } elseif (config('mail.default_notification_email') !== null) {
                Mail::to(config('mail.default_notification_email'))->send(new Notify($data));
            }
        }
    }

    /**
     * Отправка уведомления в телеграм
     *
     * @param array $notifyData тема уведомления (первый элемент массива) + текст уведомления (всё остальное, каждый элемент - новая строка)
     * @param string|null $chatId Id чата-адресата
     * @return void
     */
    public function sendTelegramNotify(array $notifyData, string $chatId = null): void
    {
        // Отправка уведомлений, если разрешено
        if (config('enable.telegramNotifications') === true) {
            // * Сборка уведомления
            $isEmpty = empty($notifyData);
            if (!$isEmpty) {
                foreach ($notifyData as $key => $value) {
                    $notifyData[$key] = array_key_first($notifyData) === $key ? $value . ' [' . env('APP_NAME', 'laravel') . ']' : $key . ') ' . $value;
                }
            }
            $notifyData = $isEmpty ? 'Тело уведомления отсутствует' : implode(PHP_EOL, $notifyData);

            // * Отправка уведомления
            Telegram::sendMessage([
                'chat_id' => $chatId ?? env('TELEGRAM_CHANNEL_ID', ''),
                'parse_mode' => 'html',
                'text' => $notifyData,
            ]);
        }
    }

// ****************************************************************************
//                                Пагинация
// ****************************************************************************

    /**
     * Построение условий keyset-pagination для нескольких сортировок
     *
     * @param Builder $builder Построитель запроса
     * @param array $sort Данные для сортировки
     * @param Model $pivot Данные последней записи на текущей странице
     * @param int $i Индекс массива с данными для сортировок
     * @return void
     *
     * Пример логики:
     *     $sort = [
     *         0 => [
     *             'field' => A
     *             'order' => 'desc'
     *         ],
     *         1 => [
     *             'field' => B
     *             'order' => 'desc'
     *         ],
     *         2 => [
     *             'field' => C
     *             'order' => 'desc'
     *         ],
     *     ]
     * Итоговый построитель запроса:
     * (A > pivot.A)
     * OR (A = pivot.A AND B > pivot.B)
     * OR (A = pivot.A AND B = pivot.B AND C > pivot.C)
     */
    protected function applyMultiFieldKeyset(Builder $builder, array $sort, Model $pivot, int $i): void
    {
        // Если при рекурсии рассмотрены все элементы множественной сортировки или если нет ни одного элемента сортировки, то ничего не делать
        if (!isset($sort[$i])) {
            return;
        }

        // Название поля сортировки
        $field = $sort[$i]['field'];
        // Направление сортировки
        $order = $sort[$i]['order'];

        // Значение поля пограничной записи
        $pivotValue = $pivot->{$field};
        // Значение оператора сравнения в соответствии с требуемым направлением сортировки
        $operator = $order === 'asc' ? '>' : '<';

        if ($i === 0) {
            // Первое условие: просто сравнение по первому полю
            $builder->where($field, $operator, $pivotValue);
        } else {
            // Вложенные условия: равенство по предыдущим полям + сравнение по текущему
            $builder->orWhere(function ($subBuilder) use ($field, $operator, $pivotValue, $sort, $pivot, $i) {
                // Условия равенства для всех предыдущих полей
                for ($j = 0; $j < $i; $j++) {
                    $prevField = $sort[$j]['field'];
                    $prevValue = $pivot->{$prevField};
                    $subBuilder->where($prevField, '=', $prevValue);
                }
                // И сравнение для текущего поля
                $subBuilder->where($field, $operator, $pivotValue);
            });
        }

        // Рекурсивный вызов для следующего поля
        $this->applyMultiFieldKeyset($builder, $sort, $pivot, $i + 1);
    }

// ****************************************************************************
//                                Фильтрация
// ****************************************************************************

    /**
     * Фильтрация
     *
     * @param Builder $builder Построитель запроса
     * @param array $filters Массив фильтров из запроса
     * @param array $filtersFormat Формат данных
     * @return void
     */
    protected function applyFiltersToQuery(Builder $builder, array $filters, array $filtersFormat): void
    {
        foreach ($filters as $key => $value) {
            switch ($filtersFormat[$key]['type']) {
                // Фильтрация по значению
                case 'in':
                    // Если идёт поиск по пустому значению
                    if (in_array(null, $value, true)) {
                        // Удалить элемент с null значением
                        unset($value[array_search(null, $value, true)]);
                        if (empty($value)) {
                            $builder->whereNull($filtersFormat[$key]['column']);
                        } else {
                            $builder->whereIn($filtersFormat[$key]['column'], $value)->orWhereNull($filtersFormat[$key]['column']);
                        }
                        break;
                    }

                    $builder->whereIn($filtersFormat[$key]['column'], $value);
                    break;

                // Фильтрация по шаблону
                case 'like':
                    $builder->where($filtersFormat[$key]['column'], 'like', '%' . addslashes($value) . '%');
                    break;

                // Фильтрация по дате
                case 'date':
                    $from = $value[0];
                    $to = $value[1];

                    if ($from) {
                        $builder->where($filtersFormat[$key]['column'], '>=', $from);
                    }
                    if ($to) {
                        $builder->where($filtersFormat[$key]['column'], '<=', $to);
                    }
                    break;

                // Фильтрация "От"
                case 'from':
                    $builder->where($filtersFormat[$key]['column'], '>=', $value);
                    break;

                // Фильтрация "До"
                case 'to':
                    $builder->where($filtersFormat[$key]['column'], '<=', $value);
                    break;
            }
        }
    }

// ****************************************************************************
//                                Словари
// ****************************************************************************

    /**
     * Словари
     *
     * @param array $ids Id сущностей, для которых необходим словарь
     * @return array|array[]
     *
     * Пример $ids:
     *     $ids = [
     *         'employers' => [3, ...],
     *         'areas' => [5, ...],
     *     ]
     */
    protected function getDictionaries(array $ids): array
    {
        // Начальные значения
        $dictionaries = [];

        // Работодатели
        if (!empty($ids['employers'])) {
            $rows = DB::table('employers')
                ->select('id', 'name', 'site_url', 'logo_path')
                ->whereIn('id', $ids['employers'])
                ->get();

            foreach ($rows as $row) {
                // Формирование URL логотипа компании
                $logoUrl = isset($row->logo_path) ? rtrim(config('hh.cdn_host'), '/') . $row->logo_path : null;

                $dictionaries['employers'][$row->id] = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'siteUrl' => $row->site_url,
                    'logoUrl' => $logoUrl,
                ];
            }
        }

        // Регионы
        if (!empty($ids['areas'])) {
            $rows = DB::table('areas')
                ->select('id', 'name')
                ->whereIn('id', $ids['areas'])
                ->get();
            foreach ($rows as $row) {
                $dictionaries['areas'][$row->id] = [
                    'id' => $row->id,
                    'name' => $row->name,
                ];
            }
        }

        // Страны
        if (!empty($ids['countries'])) {
            $rows = DB::table('areas')
                ->select('id', 'name')
                ->whereIn('id', $ids['countries'])
                ->get();
            foreach ($rows as $row) {
                $dictionaries['countries'][$row->id] = [
                    'id' => $row->id,
                    'name' => $row->name,
                ];
            }
        }

        return $dictionaries;
    }
}
