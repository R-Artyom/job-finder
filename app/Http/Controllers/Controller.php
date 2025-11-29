<?php

namespace App\Http\Controllers;

use App\Mail\Notify;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Telegram\Bot\Laravel\Facades\Telegram;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

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
     * @return void
     */
    public function sendTelegramNotify(array $notifyData): void
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
                'chat_id' => env('TELEGRAM_CHANNEL_ID', ''),
                'parse_mode' => 'html',
                'text' => $notifyData,
            ]);
        }
    }

    /**
     * Получение словарей для конечного результата табличного метода
     *
     * @param array|Collection $data отфильтрованная и срезанная коллекция (или массив)
     * @param array $filterOptions опции фильтрации полей
     * @param array $dictionariesAll полный словарь, из которого необходимо брать значения
     * @param array $dictionariesFormat формат словарей
     * @return array
     */
    protected function getResultDictionaries(array|Collection $data, array $filterOptions, array $dictionariesAll, array $dictionariesFormat): array
    {
        $dictionaries = [];
        if (!empty($dictionariesFormat)) {
            // Если для опций не нужен словарь
            if (empty($filterOptions)) {
                $dictionaries = $this->getDataObjectsDictionaries($data, $dictionariesAll, $dictionariesFormat);
            } else {
                // * Определение формата словарей для данных с объектами
                // Получение всех полей опций фильтрации
                $filterOptionsFields = [];
                foreach ($filterOptions as $field => $value) {
                    $filterOptionsFields[] = $field;
                }
                // Расхождение массивов - нужны словари только на те поля, которых нет в опциях фильтрации
                $dataDictionariesFormat = array_diff_key($dictionariesFormat, array_flip($filterOptionsFields));

                // * Получить словари для опций фильтрации
                $dictionaries = $this->getFilterOptionsDictionaries($filterOptions, $dictionariesAll, $dictionariesFormat);

                // * Добавить словари для данных с объектами
                $dictionaries = array_merge($dictionaries, $this->getDataObjectsDictionaries($data, $dictionariesAll, $dataDictionariesFormat));
            }
        }
        return $dictionaries;
    }

    /**
     * Получение словарей для всех опций фильтрации
     *
     * @param array $filterOptions опции фильтрации полей
     * @param array $dictionariesAll полный словарь, из которого необходимо брать значения
     * @param array $dictionariesFormat формат словарей
     * @return array
     */
    protected function getFilterOptionsDictionaries(array $filterOptions, array $dictionariesAll, array $dictionariesFormat): array
    {
        $dictionaries = [];
        if (!empty($dictionariesFormat)) {
            foreach ($filterOptions as $field => $options) {
                if (!empty($options)) {
                    // Если нужен словарь для такого поля
                    if (array_key_exists($field, $dictionariesFormat)) {
                        // Перебор всех опций поля
                        foreach ($options as $option) {
                            // Название словаря
                            $dictionaryName = $dictionariesFormat[$field];
                            // Отображать только непустое значение, иначе ключ = null или ""
                            if (isset($option)) {
                                $dictionaries[$dictionaryName][$option] = $dictionariesAll[$dictionaryName][$option];
                            }
                        }
                    }
                }
            }
        }
        return $dictionaries;
    }

    /**
     * Получение словарей для объектов поля "data" отфильтрованной и срезанной коллекции
     *
     * @param array|Collection $data отфильтрованная и срезанная коллекция / массив
     * @param array $dictionariesAll полный словарь, из которого необходимо брать значения
     * @param array $dictionariesFormat формат словарей
     * @return array
     */
    protected function getDataObjectsDictionaries(array|Collection $data, array $dictionariesAll, array $dictionariesFormat): array
    {
        $dictionaries = [];
        if (!empty($dictionariesFormat)) {
            // Для отфильтрованной и срезанной коллекции:
            foreach ($data as $item) {
                // Пробежка по полям объекта
                foreach ($item as $field => $value) {
                    // Если нужен словарь для поля
                    if (isset($dictionariesFormat[$field])) {
                        // Название словаря
                        $dictionaryName = $dictionariesFormat[$field];
                        // Если значение поля - массив
                        if (is_array($value)) {
                            foreach ($value as $arrayValue) {
                                if (isset($arrayValue)) {
                                    // Отображать только непустое значение, иначе ключ = null или ""
                                    $dictionaries[$dictionaryName][$arrayValue] = $dictionariesAll[$dictionaryName][$arrayValue];
                                }
                            }
                        // Если значение поля - НЕ массив
                        } else {
                            // Отображать только непустое значение, иначе ключ = null или ""
                            if (isset($value)) {
                                $dictionaries[$dictionaryName][$value] = $dictionariesAll[$dictionaryName][$value];
                            }
                        }
                    }
                }
            }
        }
        return $dictionaries;
    }
}
