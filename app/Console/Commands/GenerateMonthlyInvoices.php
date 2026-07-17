<?php

namespace App\Console\Commands;

use App\Support\MonthlyInvoiceGenerator;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'fees:generate-monthly
                            {month? : Billing month as Y-m (defaults to the current month)}';

    protected $description = 'Generate monthly fee invoices for all active students (skips existing)';

    public function handle(): int
    {
        $month = $this->argument('month') ?? now()->format('Y-m');

        try {
            $result = MonthlyInvoiceGenerator::generate($month.'-01');
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Billing %s: created %d invoice(s), skipped %d.',
            $result['billing_month'],
            $result['created'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
