<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Rules\ScannableUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site ? $this->user()->can('update', $site) : true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048', new ScannableUrl],
            'sitemap_url' => ['nullable', 'string', 'max:2048', new ScannableUrl],
            'max_pages' => ['required', 'integer', 'min:1', 'max:'.config('curbcut.max_pages_limit')],
            'scan_frequency' => ['required', Rule::in(Site::FREQUENCIES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('url'));
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $this->merge([
            'url' => $url,
            'sitemap_url' => $this->input('sitemap_url') ?: null,
            'max_pages' => $this->input('max_pages', 50),
            'scan_frequency' => $this->input('scan_frequency', 'weekly'),
        ]);
    }
}
