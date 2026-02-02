<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case ON_DEMAND = 'on-demand';
}
