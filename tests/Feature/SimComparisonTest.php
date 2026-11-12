<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SimComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_provider_report_with_valid_headers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $country = Country::create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'currency' => 'GBP',
        ]);
        $operator = Operator::create([
            'name' => 'GiffGaff',
            'slug' => 'giffgaff',
            'provider_code' => 'GG',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        $csvContent = "Provider,Mobile,Serial,Country\nGiffGaff,07123456789,SN12345,UK\n";
        $file = UploadedFile::fake()->createWithContent('provider_report.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('admin.sim-comparisons.store'), [
            'operator_ids' => [$operator->id],
            'reconciliation_month' => now()->format('Y-m'),
            'report_file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sim_provider_comparisons', [
            'admin_id' => $admin->id,
            'file_name' => 'provider_report.csv',
        ]);
    }

    public function test_upload_is_rejected_when_mandatory_headers_are_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $country = Country::create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'currency' => 'GBP',
        ]);
        $operator = Operator::create([
            'name' => 'GiffGaff',
            'slug' => 'giffgaff',
            'provider_code' => 'GG',
            'country_id' => $country->id,
            'is_active' => true,
        ]);

        $csvContent = "Provider,Mobile\nGiffGaff,07123456789\n";
        $file = UploadedFile::fake()->createWithContent('invalid_report.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('admin.sim-comparisons.store'), [
            'operator_ids' => [$operator->id],
            'reconciliation_month' => now()->format('Y-m'),
            'report_file' => $file,
        ]);

        $response->assertSessionHasErrors('report_file');
        $this->assertDatabaseCount('sim_provider_comparisons', 0);
    }
}
