<?php

namespace App\Http\Controllers;

use App\Mail\Notify;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Mail;

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
}
