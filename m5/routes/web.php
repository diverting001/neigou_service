<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use Illuminate\Http\Request;

$router->head('/', function () {
    return [];
});

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->post('/config', function (Request $request) {
    if ($request->getContent() != "neigou@123") {
        return [];
    }
    return config('neigou');
});

