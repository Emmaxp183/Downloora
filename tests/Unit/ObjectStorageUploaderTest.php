<?php

use App\Services\Storage\ObjectStorageUploader;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('it stores objects through the configured filesystem disk in tests', function () {
    Storage::fake('s3');

    $path = tempnam(sys_get_temp_dir(), 'downloora-upload-test-');
    file_put_contents($path, 'uploaded bytes');

    $mimeType = app(ObjectStorageUploader::class)->uploadFile('users/1/example.txt', $path);

    expect($mimeType)->toBe('text/plain');

    Storage::disk('s3')->assertExists('users/1/example.txt');

    @unlink($path);
});
