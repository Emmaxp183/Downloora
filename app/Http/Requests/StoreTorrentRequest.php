<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'url' => [
                'nullable',
                'required_without_all:torrent_file,magnet_uri',
                'string',
                'max:2048',
            ],
            'magnet_uri' => [
                'nullable',
                'string',
                'max:2048',
            ],
            'torrent_file' => [
                'nullable',
                'required_without_all:url,magnet_uri',
                'file',
                'max:5120',
                'extensions:torrent',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $url = $this->downloadUrl();

                if ($url === null) {
                    return;
                }

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

    public function downloadUrl(): ?string
    {
        $url = $this->string('url')->trim()->toString()
            ?: $this->string('magnet_uri')->trim()->toString();

        return $url === '' ? null : $url;
    }

    public function isMagnet(): bool
    {
        $url = $this->downloadUrl();

        return $url !== null && str_starts_with($url, 'magnet:?');
    }

    public function isMediaUrl(): bool
    {
        return $this->downloadUrl() !== null && ! $this->isMagnet();
    }
}
