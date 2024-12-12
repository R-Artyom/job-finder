<?php

namespace App\Http\Controllers\Employers;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use Carbon\Carbon;

class StoreController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke($data)
    {
        $employer = new Employer();

        // Id работодателя
        if (!empty($data['id'])) {
            $employer->id = $data['id'];
        }
        // Название работодателя
        if (!empty($data['name'])) {
            $employer->name = $data['name'];
        }
        // Регион
        if (!empty($data['area']['id'])) {
            $employer->area_id = $data['area']['id'];
        }
        // Сайт
        if (!empty($data['site_url'])) {
            $employer->site_url = $data['site_url'];
        }
        // Дата создания работодателя
        if (!empty($data['created_at'])) {
            $employer->created_at = Carbon::parse($data['created_at'])->setTimezone('UTC')->format('Y-m-d H:i:s');
        }
        // Оригинальная дата создания работодателя
        if (!empty($data['updated_at'])) {
            $employer->updated_at = now()->setTimezone('UTC')->format('Y-m-d H:i:s');
        }
        // Дата создания записи в БД
        if (!empty($data['updated_at'])) {
            $employer->updated_at = now()->setTimezone('UTC')->format('Y-m-d H:i:s');
        }

        // Создание работодателя
        $employer->save();
    }
}
