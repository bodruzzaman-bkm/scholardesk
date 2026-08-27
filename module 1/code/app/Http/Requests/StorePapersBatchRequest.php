<?php

namespace App\Http\Requests;

use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Bulk PDF upload: many papers in one go.
 *
 * The size rules come from UploadLimits rather than being hard-coded, because
 * PHP rejects an oversized request before Laravel ever sees it. A rule more
 * generous than php.ini would promise capacity the server cannot deliver.
 */
class StorePapersBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.UploadLimits::effectiveMaxFiles()],
            'files.*' => [
                'file',
                'mimetypes:application/pdf',
                'mimes:pdf',
                'max:'.UploadLimits::perFileKb(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Choose at least one PDF to upload.',
            'files.max' => 'You can upload at most '.UploadLimits::effectiveMaxFiles().' PDFs at a time on this server.',
            'files.*.mimetypes' => 'Every file must be a PDF.',
            'files.*.mimes' => 'Every file must be a PDF.',
            'files.*.max' => 'Each PDF must be '.UploadLimits::perFileLabel().' or smaller on this server.',
        ];
    }
}
