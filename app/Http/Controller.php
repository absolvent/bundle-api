<?php

namespace Absolvent\api\Http;

use Absolvent\api\AppSwaggerSchema;
use Absolvent\swagger\RequestParameters;
use Absolvent\swagger\SwaggerSchema;
use Absolvent\swagger\SwaggerValidator;
use Absolvent\swagger\SwaggerValidator\HttpRequest as HttpRequestValidator;
use Absolvent\swagger\SwaggerValidator\HttpResponse as HttpResponseValidator;
use App;
use Illuminate\Routing\Controller as BaseController;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller extends BaseController
{
    abstract public function createResponse(stdClass $parameters): Response;

    public static function validateHttpRequest(SwaggerSchema $swaggerSchema, SwaggerValidator $swaggerValidator): void
    {
        $httpRequestValidtionResult = $swaggerValidator->validateAgainst($swaggerSchema);
        if (!$httpRequestValidtionResult->isValid()) {
            throw $httpRequestValidtionResult->getException();
        }
    }

    public static function validateHttpResponse(SwaggerSchema $swaggerSchema, SwaggerValidator $swaggerValidator): void
    {
        // validate data according to Swagger schema then throw invalid data
        // exception on failure; assertions are zero-cost in production,
        // duplicate code is used to fully utilize performance advantages of
        // 'assert' language construct (PHP7+)
        // http://php.net/manual/en/function.assert.php
        assert(
            $swaggerValidator->validateAgainst($swaggerSchema)->isValid(),
            $swaggerValidator->validateAgainst($swaggerSchema)->getException()
        );
    }

    public function handleRequest(Request $request): Response
    {
        // do not pass global SwaggerSchema through controller to make it
        // easier for a developer to use (and be more defensive):
        // 1. passing SwaggerSchema object through controller constructor would
        //    no longer be necessary
        // 2. it would be harder to override application swagger schema than
        //    as if it would be assigned to some class property
        $swaggerSchema = App::make(AppSwaggerSchema::class);

        static::validateHttpRequest($swaggerSchema, new HttpRequestValidator($request));

        $parameters = (new RequestParameters($request))->getDataBySwaggerSchema($swaggerSchema);
        $response = $this->createResponse($parameters);

        static::validateHttpResponse($swaggerSchema, new HttpResponseValidator($request, $response));

        return $response;
    }
}
