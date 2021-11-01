<?php
require_once __DIR__ . '/vendor/autoload.php';

use Utopia\App;
use Utopia\Swoole\Request;
use Utopia\Swoole\Response;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Utopia\CLI\Console;

App::get('/')
    ->inject('request')
    ->inject('response')
    ->action(
        function($request, $response) {
            $response
              ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->addHeader('Expires', '0')
              ->addHeader('Pragma', 'no-cache')
              ->json(['Hello' => 'World']);
        }
    );

App::setMode(App::MODE_TYPE_DEVELOPMENT); // Define Mode

$http = new Server("0.0.0.0", 8080);

$http->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) {
    $request = new Request($swooleRequest);
    $response = new Response($swooleResponse);
    $app = new App('UTC');

    App::error(function ($error, $utopia, $request, $response) {
        /** @var Exception $error */
        /** @var Utopia\App $utopia */
        /** @var Utopia\Swoole\Request $request */
        /** @var Utopia\Swoole\Response $response */

        if ($error instanceof PDOException) {
            throw $error;
        }

        $route = $utopia->match($request);

        Console::error('[Error] Timestamp: ' . date('c', time()));

        if ($route) {
            Console::error('[Error] Method: ' . $route->getMethod());
        }

        Console::error('[Error] Type: ' . get_class($error));
        Console::error('[Error] Message: ' . $error->getMessage());
        Console::error('[Error] File: ' . $error->getFile());
        Console::error('[Error] Line: ' . $error->getLine());

        $version = App::getEnv('_APP_VERSION', 'UNKNOWN');

        $code = $error->getCode();
        $message = $error->getMessage();

        $output = ((App::isDevelopment())) ? [
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTrace(),
            'version' => $version,
        ] : [
            'message' => $message,
            'code' => $code,
            'version' => $version,
        ];

            $response
              ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->addHeader('Expires', '0')
              ->addHeader('Pragma', 'no-cache')
              ->json(
                $output
            );
    }, ['error', 'utopia', 'request', 'response']);

    try {
        $app->run($request, $response);
    } catch (Exception $e) {
        Console::error('There\'s a problem with ' . $request->getURI());
        $swooleResponse->end('500: Server Error');
    }
});

$http->start();