<?php

namespace App\Reports\Contracts;

use App\Models\Company;
use App\Models\Customer;

interface ReportInterface
{
    public static function getRequiredDataSources(): array;

    public static function getRequiredHistoricalRange(): int;

    public function validate(Customer $customer): bool;

    public function generate(Customer $customer, $period): array;

    public function getTemplate(): string;

    public function deliver(Company $company, Customer $customer, string $filePath): void;
}
