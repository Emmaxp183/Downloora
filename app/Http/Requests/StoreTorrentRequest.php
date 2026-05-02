<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTorrentRequest extends FormRequest
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
            'magnet_uri' => [
                'nullable',
                'required_without:torrent_file',
                'string',
                'starts_with:magnet:?',
            ],
            'torrent_file' => [
                'nullable',
                'required_without:magnet_uri',
                'file',
                'max:5120',
                'extensions:torrent',
            ],
        ];
    }
}
