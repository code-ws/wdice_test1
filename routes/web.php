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
/**
 * @var \Laravel\Lumen\Routing\Router $router
 */
/** Base */
$router->get('/', function () use ($router) {
    return time();
});

$router->group(['prefix' => 'api'],function () use ($router){
    $router->get('/upgrade/check','Base\UpgradeController@check');
});

$router->get('/connect',"Base\SupportController@connect");

$router->group(['prefix' => 'api',], function () use ($router){

    $router->group([
        'prefix' => 'support',
        'namespace' => 'Base',
        'middleware' => 'auth',
    ],function () use ($router){
        $router->post('/idfa_appsflyer_id',"SupportController@saveIdfaAndAppsflyerId");
        $router->post('/onesignal_id',"SupportController@saveOnesignalId");
    });

    $router->post('/log',"Base\SupportController@saveClientLog");
    $router->get('/device/setting','Base\SupportController@getSetting');
    $router->post('/device/setting','Base\SupportController@setSetting');

});

$router->get('/test/configs',"TestController@testConfigs");

/** Test */
$router->get('/test','TestController@test');
