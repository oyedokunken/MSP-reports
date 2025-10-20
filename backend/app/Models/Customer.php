<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'company_id',
        'canonical_name',
        'status',
        'report_configuration',
        'report_destination',
        'metadata',
    ];

    protected $casts = [
        'report_configuration' => 'array',
        'metadata' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    // Many-to-Many through CustomerMappings
    public function integrationCustomers()
    {
        return $this->belongsToMany(
            IntegrationCustomer::class,
            'customer_mappings',
            'customer_id',
            'integration_customer_id'
        );
    }

    public function customerMappings()
    {
        return $this->hasMany(CustomerMapping::class, 'customer_id', 'customer_id');
    }

    public function reportSchedules()
    {
        return $this->hasMany(ReportSchedule::class, 'customer_id', 'customer_id');
    }

    public function generatedReports()
    {
        return $this->hasMany(GeneratedReport::class, 'customer_id', 'customer_id');
    }
}
