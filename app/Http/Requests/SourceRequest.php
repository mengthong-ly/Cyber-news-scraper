<?php

namespace App\Http\Requests;

use App\Support\HostResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class SourceRequest extends FormRequest
{
    /** Settings each source type needs. */
    public const REQUIRED_SETTINGS = [
        'rss' => ['url'],
        'google_news' => ['query'],
        'html_xpath' => ['url', 'item', 'title'],
        'bluesky' => ['queries'],
        'youtube' => ['queries'],
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->canAnalyze();
    }

    protected function prepareForValidation(): void
    {
        $config = $this->input('config');

        if (is_string($config)) {
            $decoded = json_decode($config === '' ? '{}' : $config, true);
            $this->merge(['config' => is_array($decoded) ? $decoded : $config]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([...array_keys(config('cyber.adapters')), 'manual'])],
            'config' => ['present', 'array', $this->requiredSettings(...)],
            'config.url' => ['sometimes', 'url:http,https', $this->publicHost(...)],
            'interval_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'enabled' => ['boolean'],
            'ai_enabled' => ['boolean'],
        ];
    }

    /**
     * validated() keeps only the config keys that have their own rule, so take the whole validated object.
     *
     * @return array<string, mixed>
     */
    public function sourceAttributes(): array
    {
        return [...$this->validated(), 'config' => $this->input('config')];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['config.array' => 'Settings must be a JSON object.'];
    }

    private function requiredSettings(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (self::REQUIRED_SETTINGS[$this->input('type')] ?? [] as $key) {
            if (blank($value[$key] ?? null)) {
                $fail("Settings must include \"{$key}\" for this source type.");
            }
        }
    }

    /**
     * Sources are fetched from the server, so refuse internal targets early (the fetch checks again).
     */
    private function publicHost(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(HostResolver::class)->resolvePublic((string) $value);
        } catch (RuntimeException) {
            $fail('The URL must point to a public internet host.');
        }
    }
}
