<?php

namespace App\Util;

use App\Models\ConnectionAttributeDefinition;

/**
 * Validates and coerces a raw attribute value against its definition's type/options.
 * Values are encrypted at rest, so Postgres can't enforce type/range/membership via
 * CHECK constraints - all of that has to happen here, in PHP, before encryption.
 */
class ConnectionAttributeValidator
{
    /**
     * @return array{0: mixed, 1: string|null} [typed value or null, error message or null]
     */
    public static function validate(ConnectionAttributeDefinition $definition, mixed $raw): array
    {
        $options = $definition->options ?? [];

        switch ($definition->type) {
            case 'number':
                if (!is_numeric($raw)) {
                    return [null, "\"{$definition->label}\" must be a number."];
                }
                $value = $raw + 0;
                if (isset($options['min']) && $value < $options['min']) {
                    return [null, "\"{$definition->label}\" must be at least {$options['min']}."];
                }
                if (isset($options['max']) && $value > $options['max']) {
                    return [null, "\"{$definition->label}\" must be at most {$options['max']}."];
                }
                return [$value, null];

            case 'numeric_range':
                if (!is_numeric($raw)) {
                    return [null, "\"{$definition->label}\" must be a number."];
                }
                $value = $raw + 0;
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                if ($min !== null && $value < $min) {
                    return [null, "\"{$definition->label}\" must be at least {$min}."];
                }
                if ($max !== null && $value > $max) {
                    return [null, "\"{$definition->label}\" must be at most {$max}."];
                }
                return [$value, null];

            case 'enum':
            case 'radio':
                $choices = $options['choices'] ?? [];
                if (!is_string($raw) || !in_array($raw, $choices, true)) {
                    return [null, "\"{$definition->label}\" must be one of the defined options."];
                }
                return [$raw, null];

            case 'text':
            case 'textarea':
                if (!is_string($raw)) {
                    return [null, "\"{$definition->label}\" must be text."];
                }
                return [$raw, null];

            case 'boolean':
                if (is_bool($raw)) {
                    return [$raw, null];
                }
                $normalized = is_string($raw) ? strtolower(trim($raw)) : $raw;
                if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                    return [true, null];
                }
                if (in_array($normalized, ['0', 'false', 'off', 'no', ''], true)) {
                    return [false, null];
                }
                return [null, "\"{$definition->label}\" must be a yes/no value."];

            default:
                return [null, "Unknown attribute type for \"{$definition->label}\"."];
        }
    }
}
