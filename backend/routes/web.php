<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => '在线课程教务系统',
        'version' => '1.0.0',
        'feature' => '国内小批量MOQ直发',
        'docs' => '/api/documentation',
    ]);
});
