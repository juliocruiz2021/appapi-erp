<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\TenantProvisioningService;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
    ) {
    }

    public function run(): void
    {
        $result = $this->tenantProvisioningService->ensureTenantWithAdmin([
            'company_name' => 'Demo1 Company',
            'tenant_id' => 'demo1',
            'path' => 'demo1',
            'admin_name' => 'Demo1 Admin',
            'admin_email' => 'admin@demo1.com',
            'admin_password' => 'password123',
        ]);

        $this->command?->info(sprintf(
            'Tenant %s listo en BD %s con admin %s',
            $result['tenant']->id,
            $result['database'],
            $result['admin']->email,
        ));
    }
}
