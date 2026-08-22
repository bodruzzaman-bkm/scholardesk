<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Matches ScholarDesk's @@unique([userId, name]) constraint.
                Rule::unique('tags', 'name')->where('user_id', Auth::id()),
            ],
            // Reject anything that is not a real hex colour before it reaches
            // a style attribute in a Blade view.
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a tag with that name.',
            'color.regex' => 'Pick a colour in #RRGGBB format.',
        ];
    }
}
