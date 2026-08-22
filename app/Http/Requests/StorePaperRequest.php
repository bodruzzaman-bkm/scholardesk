<?php

namespace App\Http\Requests;

use App\Support\Doi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StorePaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * One field accepts either a DOI or an article URL (requirement 2), which
     * is how a researcher actually works - they paste whatever they copied.
     * MetadataService decides which it is.
     *
     * A bare DOI is normalised here so that "https://doi.org/10.1/X",
     * "doi:10.1/x" and "10.1/X" all collide on the uniqueness rule below
     * instead of creating duplicate papers.
     */
    protected function prepareForValidation(): void
    {
        // `doi` is still accepted as an alias so older links and forms work.
        $identifier = trim((string) ($this->input('identifier') ?? $this->input('doi') ?? ''));

        if ($identifier === '') {
            return;
        }

        $this->merge([
            'identifier' => $this->isUrl($identifier) ? $identifier : (Doi::normalize($identifier) ?? $identifier),
        ]);
    }

    public function rules(): array
    {
        $identifier = (string) $this->input('identifier', '');
        $isUrl = $this->isUrl($identifier);

        return [
            'identifier' => array_values(array_filter([
                'required_without:file',
                'nullable',
                'string',
                'max:2048',
                $isUrl ? 'url' : null,
                /*
                 | Only a pasted DOI can be checked for duplicates up front.
                 | A URL's DOI is not known until it has been resolved, so that
                 | case is caught in PaperService instead.
                 */
                $isUrl ? null : Rule::unique('papers', 'doi')->where('user_id', Auth::id()),
            ])),
            'file' => ['required_without:identifier', 'nullable', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.unique' => 'That DOI is already in your library.',
            'identifier.url' => 'That does not look like a valid link.',
            'identifier.required_without' => 'Paste a DOI or article link, or upload a PDF.',
            'file.required_without' => 'Upload a PDF, or paste a DOI or article link.',
            'file.mimetypes' => 'The uploaded file must be a PDF.',
            'file.max' => 'The PDF may not be larger than 10 MB.',
        ];
    }

    public function attributes(): array
    {
        return ['identifier' => 'DOI or link'];
    }

    private function isUrl(string $value): bool
    {
        // A doi.org link is a DOI wearing a URL costume; treat it as a DOI so
        // the uniqueness rule still applies.
        if (preg_match('#^https?://(dx\.)?doi\.org/#i', $value) === 1) {
            return false;
        }

        return preg_match('#^https?://#i', $value) === 1;
    }
}
