<?php

namespace App\Services\AI\Concerns;

trait FormatsAiPromptContext
{
    protected function formatPromptContextValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $text = trim((string) $item);
                    if ($text !== '') {
                        $items[] = $text;
                    }
                    continue;
                }
                $encoded = trim(json_encode($item));
                if ($encoded !== '') {
                    $items[] = $encoded;
                }
            }

            return implode(', ', $items);
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return trim((string) $value);
    }
}
