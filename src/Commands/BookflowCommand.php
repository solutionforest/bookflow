<?php

namespace SolutionForest\Bookflow\Commands;

use Illuminate\Console\Command;

class BookflowCommand extends Command
{
    public $signature = 'bookflow';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
