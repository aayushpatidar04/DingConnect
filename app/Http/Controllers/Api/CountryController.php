<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends Controller
{
    public function index(): JsonResponse
    {
        $countries = DB::table('countries')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code', 'calling_code', 'currency', 'flag_emoji']);

        return response()->json(['success' => true, 'data' => $countries]);
    }
}
