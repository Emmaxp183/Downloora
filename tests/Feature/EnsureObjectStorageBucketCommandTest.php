<?php

test('it skips bucket checks when the default filesystem is not s3', function () {
    config(['filesystems.default' => 'local']);

    $this->artisan('storage:ensure-s3-bucket')
        ->expectsOutputToContain('Default filesystem is not S3')
        ->assertSuccessful();
});
