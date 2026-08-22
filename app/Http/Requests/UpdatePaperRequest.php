<?php

namespace App\Http\Requests;

use App\Enums\ReadingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePaperRequest extends FormRequest
{
    /** Authorisation is handled by PaperPolicy via the controller. */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $userId = Auth::id();

        return [
            'title' => ['required', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:1000'],
            'year' => ['nullable', 'integer', 'min:1500', 'max:'.(date('Y') + 1)],
            'venue' => ['nullable', 'string', 'max:255'],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'reading_status' => ['required', new Enum(ReadingStatus::class)],

            // Every id must belong to the current user, otherwise a crafted
            // form could attach a paper to somebody else's collection or tag.
            'collections' => ['nullable', 'array'],
            'collections.*' => [
                'integer',
                Rule::exists('collections', 'id')->where('user_id', $userId),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('user_id', $userId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Covers both "belongs to someone else" and "was deleted in
            // another tab", which look identical from the server's side.
            'collections.*.exists' => 'One of the selected collections is no longer available.',
            'tags.*.exists' => 'One of the selected tags is no longer available.',
            'year.integer' => 'Year must be a four-digit number.',
        ];
    }
}
