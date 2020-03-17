<?php

namespace App\Exceptions;

use App\Services\JobService;
use App\Services\PotatoChatService;
use App\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        //AuthorizationException::class,
        //HttpException::class,
        //ModelNotFoundException::class,
        //ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception $e
     * @return void
     * @throws
     */
    public function report(Exception $e)
    {
        if($e->getCode() >= 800 && $e->getCode() < 1300)
        {
            Log::info($e->getMessage());
        }
        else
        {
            parent::report($e);
        }
    }

    const ERROR_DESC = [
        /*401 => '未经授权',
        403 => '访问被禁止',
        404 => '路由异常',
        405 => '方法不允许',
        422 => '数据验证失败',*/
        0 => 'system issue',
        401 => 'system issue',
        403 => 'system issue',
        404 => 'system issue',
        405 => 'system issue',
        422 => 'system issue',
        500 => 'system issue'
    ];

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $e
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request, Exception $e)
    {
        if (env('APP_DEBUG', false) === false) {
            return $this->prodRender($e);
        }
        return $this->debugRender($e,$request);
    }

    public function debugRender(Exception $e,Request $request)
    {
        Log::info($request->fullUrl());
        Log::info($request->method());
        $httpCode = 500;
        $businessCode = $e->getCode();
        if ($e instanceof HttpException) {
            $httpCode = in_array($e->getStatusCode(), array_keys(self::ERROR_DESC)) ? $e->getStatusCode() : 422;
            $businessCode = $e->getStatusCode();
        }

        $message = empty($e->getMessage()) ? $e->getTraceAsString() : $e->getMessage();

        if($businessCode == 0)
            $businessCode = 1000;
        $respBody = [
            'code' => $businessCode,
            'message' => 'fail',
            "time" => time(),
            'data' => [],
            'errors' => [$message]
        ];
        return JsonResponse::create($respBody, $httpCode);
    }

    public function prodRender(Exception $e)
    {
        $httpCode = 500;
        $businessCode = $e->getCode();
        if ($e instanceof HttpException) {
            $httpCode = in_array($e->getStatusCode(), array_keys(self::ERROR_DESC)) ? $e->getStatusCode() : 422;
            $businessCode = $e->getStatusCode();
        }
        $message = Arr::get(self::ERROR_DESC, $httpCode, 'error');
        if($businessCode == 0)
            $businessCode = 1000;
        $respBody = [
            'code' => $businessCode,
            'message' => 'fail',
            'time' => time(),
            'data' => [],
            'errors' => [$message]
        ];

        return JsonResponse::create($respBody, $httpCode);
    }
}
