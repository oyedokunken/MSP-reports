<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

/**
 * @property int $count
 * @property int $generated_report_id
 * @property int $company_id
 * @property int|null $customer_id
 * @property int|null $report_schedule_id
  * @property \Carbon\Carbon|null $generated_at
  * @property array<string, string>|null $report_period
  * @property string $status
  * @property string|null $file_path
  * @property int|null $file_size
  * @property bool $delivered
  * @property \Carbon\Carbon|null $delivered_at
  * @property string|null $delivery_method
  * @property string|null $error_message
  * @property \Carbon\Carbon|null $retention_until
  * @property-read \App\Models\Company $company
  * @property-read \App\Models\Customer|null $customer
  * @property-read \App\Models\ReportSchedule|null $reportSchedule
  */
class GeneratedReport extends Model
{
    /** @use HasFactory<\Database\Factories\GeneratedReportFactory> */
    use SoftDeletes, HasFactory;

    protected $primaryKey = 'generated_report_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

    protected $fillable = [
        'company_id',
        'customer_id',
        'report_schedule_id',
        'generated_at',
        'report_period',
        'status',
        'file_path',
        'file_size',
        'delivered',
        'delivered_at',
        'delivery_method',
        'error_message',
        'retention_until',
        'report_data',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'report_period' => 'array',
        'delivered_at' => 'datetime',
        'retention_until' => 'datetime',
        'delivered' => 'boolean',
        'report_data' => 'array',
    ];

    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Company, $this>
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Customer, $this>
     */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<ReportSchedule, $this>
     */
    public function reportSchedule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id', 'report_schedule_id');
    }
}
