<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Integration;

use App\Actions\Integration\CreateIntegrationAction;
use App\Models\Company;
use App\Models\Integration;
use App\Services\IntegrationLifecycleService;
use App\Services\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateIntegrationActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateIntegrationAction $action;
    private $integrationService;
    private $lifecycleService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->integrationService = $this->mock(IntegrationService::class);
        $this->lifecycleService = $this->mock(IntegrationLifecycleService::class);
        $this->notificationService = $this->mock(\App\Services\InAppNotificationService::class);
        
        // Allow notification calls
        $this->notificationService->shouldReceive('integrationCreated')->byDefault();

        $this->action = new CreateIntegrationAction(
            $this->integrationService,
            $this->lifecycleService,
            $this->notificationService
        );
    }

    public function test_it_onboards_a_new_integration_successfully(): void
    {
        $company = Company::factory()->create();
        
        $this->integrationService->shouldReceive('scanAvailableIntegrations')
            ->once()
            ->andReturn(['datto_rmm', 'it_glue']);

        $data = [
            'integration_type' => 'datto_rmm',
            'display_name' => 'My Test Integration',
            'credentials' => ['api_key' => 'secret'],
        ];

        $expectedIntegration = new Integration(['integration_id' => 1]);
        
        $this->lifecycleService->shouldReceive('onboardNewIntegration')
            ->once()
            ->with($company, \Mockery::on(function($arg) {
                return $arg['integration_type'] === 'datto_rmm' && $arg['status'] === 'active';
            }))
            ->andReturn($expectedIntegration);

        $result = $this->action->execute($company, $data);

        $this->assertSame($expectedIntegration, $result);
    }

    public function test_it_throws_exception_for_invalid_integration_type(): void
    {
        $company = Company::factory()->create();
        
        $this->integrationService->shouldReceive('scanAvailableIntegrations')
            ->once()
            ->andReturn(['datto_rmm']);

        $data = [
            'integration_type' => 'invalid_type',
            'display_name' => 'Test',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid integration type.');

        $this->action->execute($company, $data);
    }
}
