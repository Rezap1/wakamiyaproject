<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Historical command retained only to fail closed after the MySQL cutover. */
class UpdateHeaders extends Command
{
    protected $signature = 'sheet:update-headers';

    protected $description = 'Disabled legacy Google Sheets schema command';

    public function handle(): int
    {
        $this->error('Command dinonaktifkan: schema runtime WMS dikelola oleh migration MySQL.');

        return self::FAILURE;
    }
}
