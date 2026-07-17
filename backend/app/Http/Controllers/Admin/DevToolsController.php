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
     * Keys are the identifier sent by the frontend; values describe the Artisan command and UI metadata.
     */
    private const COMMAND_META = [
        'optimize:clear'             => ['command' => 'optimize:clear'],
        'cache:clear'                => ['command' => 'cache:clear'],
        'config:clear'               => ['command' => 'config:clear'],
        'route:clear'                => ['command' => 'route:clear'],
        'view:clear'                 => ['command' => 'view:clear'],
        'event:clear'                => ['command' => 'event:clear'],
        'migrate'                    => ['command' => 'migrate'],
        'migrate:status'             => ['command' => 'migrate:status'],
        'migrate:fresh'              => [
            'command' => 'migrate:fresh',
            'danger'  => true,
            'warning' => 'DESTRUCTIVE: Drops all tables and deletes all data, then re-runs migrations. Irreversible.',
        ],
        'queue:restart'              => ['command' => 'queue:restart'],
        'queue:drain'                => ['command' => 'queue:work --stop-when-empty'],
        'schedule:run'               => ['command' => 'schedule:run'],
        'social:publish-scheduled'   => ['command' => 'social:publish-scheduled'],
    ];

    /** Return the list of allowed commands so the UI can render them. */
    public function index(): JsonResponse
    {
        $commands = array_map(function (string $key): array {
            $meta = self::COMMAND_META[$key];
            $entry = [
                'id'      => $key,
                'command' => 'php artisan ' . $meta['command'],
                'danger'  => $meta['danger'] ?? false,
            ];

            if (! empty($meta['warning'])) {
                $entry['warning'] = $meta['warning'];
            }

            return $entry;
        }, array_keys(self::COMMAND_META));

        return ApiResponse::success(array_values($commands));
    }

    /** Run one of the whitelisted Artisan commands. */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'in:' . implode(',', array_keys(self::COMMAND_META))],
        ]);

        $commandKey     = $validated['command'];
        $artisanCommand = self::COMMAND_META[$commandKey]['command'];

        try {
            $options = [];

            // migrate commands run non-interactively in production
            if (in_array($commandKey, ['migrate', 'migrate:fresh'], true)) {
                $options = ['--force' => true];
            }

            // queue:drain uses queue:work with --stop-when-empty so it exits when queue is empty
            if ($commandKey === 'queue:drain') {
                $exitCode = Artisan::call('queue:work', ['--stop-when-empty' => true]);
            } else {
                $exitCode = Artisan::call($artisanCommand, $options);
            }
            $output = Artisan::output();

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
