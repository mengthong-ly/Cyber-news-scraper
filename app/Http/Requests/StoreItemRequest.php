<?php

namespace App\Http\Requests;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAnalyze();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
            'title' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'max:50'],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(Item::CATEGORIES)],
            'severity' => ['nullable', 'integer', 'between:1,5'],
            'is_cambodia' => ['boolean'],
            'ai_allowed' => ['boolean'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
