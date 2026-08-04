<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResponse;
use App\Support\DestructiveDatabaseGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class DevToolsController extends Controller
{
    /**
     * Commands that are safe to run from the UI.
     * Keys are the identifier sent by the frontend; values describe the Artisan command and UI metadata.
     * Commands with danger=true are omitted/blocked outside local/testing (see DestructiveDatabaseGuard).
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
            'warning' => 'DESTRUCTIVE: Drops all tables and deletes all data, then re-runs migrations. Irreversible. Blocked in production.',
        ],
        'queue:restart'              => ['command' => 'queue:restart'],
        'queue:drain'                => ['command' => 'queue:work --stop-when-empty'],
        'schedule:run'               => ['command' => 'schedule:run'],
        'social:publish-scheduled'   => ['command' => 'social:publish-scheduled'],
        'social:refresh-tokens'      => ['command' => 'social:refresh-tokens'],
        'media:backfill-cache'       => ['command' => 'media:backfill-cache'],
    ];

    /** @return array<string, array{command: string, danger?: bool, warning?: string}> */
    private function allowedCommandMeta(): array
    {
        $allowDanger = DestructiveDatabaseGuard::allowsDestructiveCommands();

        return array_filter(
            self::COMMAND_META,
            static fn (array $meta): bool => $allowDanger || empty($meta['danger'])
        );
    }

    /** Return the list of allowed commands so the UI can render them. */
    public function index(): JsonResponse
    {
        $commands = [];

        foreach ($this->allowedCommandMeta() as $key => $meta) {
            $entry = [
                'id'      => $key,
                'command' => 'php artisan ' . $meta['command'],
                'danger'  => $meta['danger'] ?? false,
            ];

            if (! empty($meta['warning'])) {
                $entry['warning'] = $meta['warning'];
            }

            $commands[] = $entry;
        }

        return ApiResponse::success($commands);
    }

    /** Run one of the whitelisted Artisan commands. */
    public function run(Request $request): JsonResponse
    {
        $allowed = $this->allowedCommandMeta();

        $validated = $request->validate([
            'command' => ['required', 'string', Rule::in(array_keys($allowed))],
        ]);

        $commandKey     = $validated['command'];
        $artisanCommand = $allowed[$commandKey]['command'];

        // Belt-and-suspenders: never run wipe commands outside local/testing.
        if (! empty($allowed[$commandKey]['danger'])) {
            DestructiveDatabaseGuard::abortIfDestructiveCommandBlocked($artisanCommand);
        }

        try {
            $options = [];

            // Non-interactive migrate (additive only — migrate:fresh is gated above).
            if ($commandKey === 'migrate') {
                $options = ['--force' => true];
            }

            if ($commandKey === 'migrate:fresh') {
                // Only reachable in local/testing; still needs --force when APP_ENV != local.
                $options = ['--force' => true];
            }

            if ($commandKey === 'media:backfill-cache') {
                $options = ['--limit' => 300];
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
