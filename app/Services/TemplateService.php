<?php

namespace App\Services;

use App\Enums\CommunicationChannel;

/**
 * Template Registry + renderer (PRD §20.8, rule 4).
 *
 * Templates live entirely in config/communication.php (the registry). This
 * service only reads them — there is no switch/match and no hard-coded list,
 * so adding a template means editing the config, not this service.
 */
class TemplateService
{
    /**
     * All registered templates keyed by their snake_case name.
     */
    public function all(): array
    {
        return config('communication.templates', []);
    }

    /**
     * Whether a template with the given key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * Render a template's title and body with the given placeholder data.
     *
     * @param  array<string,mixed>  $data
     * @return array{title: string, body: string}
     *
     * @throws \InvalidArgumentException  when the template is not registered
     */
    public function render(string $key, CommunicationChannel $channel, array $data): array
    {
        $template = $this->all()[$key] ?? null;

        if (!$template) {
            throw new \InvalidArgumentException("Template '{$key}' is not registered.");
        }

        return [
            'title' => $this->substitute($template['title'], $data),
            'body' => $this->substitute($template['body'] ?? '', $data),
        ];
    }

    /**
     * Replace `{{key}}` placeholders with $data values (missing keys become
     * empty strings). Payload is caller-supplied and never contains secrets.
     *
     * @param  array<string,mixed>  $data
     */
    protected function substitute(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($data) {
            $key = $m[1];

            return array_key_exists($key, $data) ? (string) $data[$key] : '';
        }, $text) ?? $text;
    }
}