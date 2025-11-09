<?php

namespace App\Http\Controllers\Vacancies;

use App\Http\Controllers\Controller;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return Vacancy::query()
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }
}
