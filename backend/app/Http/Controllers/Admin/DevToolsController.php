<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DevToolsController extends Controller
{
    /**
     * Commands that are safe to run from the UI.
     * Keys are the identifier sent by the frontend; values are the actual Artisan command strings.
     */
    private const ALLOWED_COMMANDS = [
        'optimize:clear'   => 'optimize:clear',
        'cache:clear'      => 'cache:clear',
        'config:clear'     => 'config:clear',
        'route:clear'      => 'route:clear',
        'view:clear'       => 'view:clear',
        'event:clear'      => 'event:clear',
        'migrate'          => 'migrate',
        'migrate:status'   => 'migrate:status',
        'queue:restart'    => 'queue:restart',
        'schedule:run'              => 'schedule:run',
        'social:publish-scheduled'  => 'social:publish-scheduled',
    ];

    /** Return the list of allowed commands so the UI can render them. */
    public function index(): JsonResponse
    {
        $commands = array_map(fn (string $key) => [
            'id'      => $key,
            'command' => 'php artisan ' . self::ALLOWED_COMMANDS[$key],
        ], array_keys(self::ALLOWED_COMMANDS));

        return ApiResponse::success(array_values($commands));
    }

    /** Run one of the whitelisted Artisan commands. */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'in:' . implode(',', array_keys(self::ALLOWED_COMMANDS))],
        ]);

        $commandKey = $validated['command'];
        $artisanCommand = self::ALLOWED_COMMANDS[$commandKey];

        try {
            $options = [];

            // migrate runs non-interactively in production
            if ($commandKey === 'migrate') {
                $options = ['--force' => true];
            }

            $exitCode = Artisan::call($artisanCommand, $options);
            $output   = Artisan::output();

            return ApiResponse::success([
                'command'   => 'php artisan ' . $artisanCommand,
                'exit_code' => $exitCode,
                'output'    => $output ?: '(no output)',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 500);
        }
    }
}
