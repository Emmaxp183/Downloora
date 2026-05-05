<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWishlistItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $url = $this->wishlistUrl();

                if (str_starts_with($url, 'magnet:?')) {
                    return;
                }

                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $validator->errors()->add('url', 'Enter a valid magnet link or public media URL.');

                    return;
                }

                $scheme = parse_url($url, PHP_URL_SCHEME);

                if (! in_array($scheme, ['http', 'https'], true)) {
                    $validator->errors()->add('url', 'Media URLs must start with http:// or https://.');
                }
            },
        ];
    }

    public function wishlistUrl(): string
    {
        return $this->string('url')->trim()->toString();
    }
}
