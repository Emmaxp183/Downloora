<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class YoutubeCookieController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->hasFile('cookies_file')) {
            return $this->storeUploadedCookieFile($request);
        }

        $validated = $request->validate([
            'cookies' => ['required', 'array', 'min:1', 'max:250'],
            'cookies.*.domain' => ['required', 'string', 'max:255'],
            'cookies.*.expirationDate' => ['nullable', 'numeric'],
            'cookies.*.hostOnly' => ['nullable', 'boolean'],
            'cookies.*.httpOnly' => ['nullable', 'boolean'],
            'cookies.*.name' => ['required', 'string', 'max:255'],
            'cookies.*.path' => ['required', 'string', 'max:255'],
            'cookies.*.sameSite' => ['nullable', 'string', 'max:32'],
            'cookies.*.secure' => ['nullable', 'boolean'],
            'cookies.*.session' => ['nullable', 'boolean'],
            'cookies.*.value' => ['required', 'string', 'max:4096'],
        ]);

        $cookies = collect($validated['cookies'])
            ->filter(fn (array $cookie): bool => $this->isAllowedDomain($cookie['domain']))
            ->unique(fn (array $cookie): string => implode('|', [
                $cookie['domain'],
                $cookie['path'],
                $cookie['name'],
            ]))
            ->values();

        if ($cookies->isEmpty()) {
            throw ValidationException::withMessages([
                'cookies' => __('No YouTube cookies were received. Open YouTube while signed in, then sync again.'),
            ]);
        }

        $path = config('media.yt_dlp.cookies');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'cookies' => __('YTDLP_COOKIES is not configured.'),
            ]);
        }

        $this->writeCookieFile($path, $this->toNetscapeCookieFile($cookies->all()), 'cookies');

        return response(status: 204);
    }

    private function storeUploadedCookieFile(Request $request): Response
    {
        $validated = $request->validate([
            'cookies_file' => ['required', 'file', 'max:2048'],
        ]);

        $contents = File::get($validated['cookies_file']->getRealPath());
        $domains = $this->netscapeCookieDomains($contents);

        if ($domains === []) {
            throw ValidationException::withMessages([
                'cookies_file' => __('Upload a Netscape cookies.txt file exported from YouTube.'),
            ]);
        }

        if (collect($domains)->contains(fn (string $domain): bool => $this->isAllowedDomain($domain)) === false) {
            throw ValidationException::withMessages([
                'cookies_file' => __('The cookies file must include YouTube cookies.'),
            ]);
        }

        $path = config('media.yt_dlp.cookies');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'cookies_file' => __('YTDLP_COOKIES is not configured.'),
            ]);
        }

        $this->writeCookieFile($path, $contents, 'cookies_file');

        return response(status: 204);
    }

    private function writeCookieFile(string $path, string $contents, string $errorKey): void
    {
        File::ensureDirectoryExists(dirname($path), 0700);

        try {
            File::replace($path, $contents, 0600);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                $errorKey => __('Unable to save YouTube cookies. Check storage permissions and try again.'),
            ]);
        }
    }

    private function isAllowedDomain(string $domain): bool
    {
        $domain = ltrim(strtolower($domain), '.');

        return $domain === 'youtube.com' || str_ends_with($domain, '.youtube.com');
    }

    /**
     * @return array<int, string>
     */
    private function netscapeCookieDomains(string $contents): array
    {
        $domains = [];

        foreach (preg_split('/\r?\n/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = preg_split('/\t+/', $line);

            if (is_array($columns) && count($columns) >= 7 && is_string($columns[0])) {
                $domains[] = $columns[0];
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @param  array<int, array{
     *     domain: string,
     *     expirationDate?: float|int|null,
     *     hostOnly?: bool,
     *     httpOnly?: bool,
     *     name: string,
     *     path: string,
     *     secure?: bool,
     *     value: string
     * }>  $cookies
     */
    private function toNetscapeCookieFile(array $cookies): string
    {
        $lines = [
            '# Netscape HTTP Cookie File',
            '# This file is generated by Downloora. Do not edit.',
            '',
        ];

        foreach ($cookies as $cookie) {
            $domain = strtolower($cookie['domain']);
            $includeSubdomains = ! ($cookie['hostOnly'] ?? false);
            $expires = (int) floor((float) ($cookie['expirationDate'] ?? 0));
            $name = str_replace(["\t", "\r", "\n"], '', $cookie['name']);
            $value = str_replace(["\t", "\r", "\n"], '', $cookie['value']);

            $lines[] = implode("\t", [
                $domain,
                $includeSubdomains ? 'TRUE' : 'FALSE',
                $cookie['path'],
                ($cookie['secure'] ?? false) ? 'TRUE' : 'FALSE',
                (string) $expires,
                $name,
                $value,
            ]);
        }

        return implode("\n", $lines)."\n";
    }
}
