<?php

namespace Tests\Feature\Api;

use App\Models\Country;
use App\Models\Operator;
use App\Models\SimProviderComparison;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimComparisonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_comparisons_and_operator_choices_as_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = $this->createOperator();
        $comparison = SimProviderComparison::create([
            'admin_id' => $admin->id,
            'operator_id' => $operator->id,
            'reconciliation_month' => '2026-11',
            'file_name' => 'provider_report.csv',
            'provider_name' => $operator->name,
            'total_internal' => 2,
            'total_provider' => 2,
            'matched_count' => 1,
            'missing_count' => 1,
            'additional_count' => 1,
            'report_data' => ['matched' => [['mobile' => '07123456789']]],
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/admin/sim-comparisons')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.comparisons.data.0.id', $comparison->id)
            ->assertJsonPath('data.comparisons.data.0.matched_count', 1)
            ->assertJsonPath('data.comparisons.data.0.operator.id', $operator->id)
            ->assertJsonPath('data.operators.0.id', $operator->id)
            ->assertJsonMissingPath('data.comparisons.data.0.report_data');
    }

    public function test_admin_uploads_provider_report_and_can_read_full_comparison(): void
    {
        Storage::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = $this->createOperator();
        $file = UploadedFile::fake()->createWithContent(
            'provider_report.csv',
            "Provider,Mobile,Serial,Country\nGiffGaff,07123456789,SN12345,UK\n",
        );
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->post('/api/admin/sim-comparisons', [
                'operator_id' => $operator->id,
                'reconciliation_month' => '2026-11',
                'report_file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.comparison.operator.id', $operator->id)
            ->assertJsonPath('data.comparison.file_name', 'provider_report.csv')
            ->assertJsonPath('data.comparison.total_provider', 1)
            ->assertJsonPath('data.comparison.additional_count', 1)
            ->assertJsonPath('data.comparison.report_data.additional_in_provider.0.mobile', '07123456789');

        $comparisonId = $response->json('data.comparison.id');
        $this->assertDatabaseHas('sim_provider_comparisons', [
            'id' => $comparisonId,
            'admin_id' => $admin->id,
            'operator_id' => $operator->id,
            'reconciliation_month' => '2026-11',
        ]);
        Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('temp');

        $this->withToken($token)
            ->getJson("/api/admin/sim-comparisons/{$comparisonId}")
            ->assertOk()
            ->assertJsonPath('data.comparison.id', $comparisonId)
            ->assertJsonPath('data.comparison.report_data.additional_in_provider.0.serial', 'SN12345');
    }

    public function test_admin_upload_with_missing_headers_returns_validation_error_without_saving(): void
    {
        Storage::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = $this->createOperator();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->post('/api/admin/sim-comparisons', [
                'operator_id' => $operator->id,
                'reconciliation_month' => '2026-11',
                'report_file' => UploadedFile::fake()->createWithContent(
                    'invalid_report.csv',
                    "Provider,Mobile\nGiffGaff,07123456789\n",
                ),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.report_file.0',
                'The uploaded provider report is missing mandatory header(s): Serial, Country. Required headers: Provider, Mobile, Serial, Country.',
            );

        $this->assertDatabaseCount('sim_provider_comparisons', 0);
        Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('temp');
    }

    public function test_admin_upload_rejects_file_with_unsupported_extension(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $operator = $this->createOperator();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->post('/api/admin/sim-comparisons', [
                'operator_id' => $operator->id,
                'reconciliation_month' => '2026-11',
                'report_file' => UploadedFile::fake()->createWithContent(
                    'provider_report.exe',
                    "Provider,Mobile,Serial,Country\nGiffGaff,07123456789,SN12345,UK\n",
                ),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('report_file');

        $this->assertDatabaseCount('sim_provider_comparisons', 0);
    }

    public function test_sim_comparison_routes_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/sim-comparisons')
            ->assertUnauthorized();

        $retailer = User::factory()->create([
            'role' => 'retailer',
            'is_active' => true,
        ]);

        $this->withToken($retailer->createToken('test')->plainTextToken)
            ->getJson('/api/admin/sim-comparisons')
            ->assertForbidden();
    }

    private function createOperator(): Operator
    {
        $country = Country::create([
            'name' => 'United Kingdom',
            'iso_code' => 'GB',
            'iso_code_3' => 'GBR',
            'currency' => 'GBP',
        ]);

        return Operator::create([
            'name' => 'GiffGaff',
            'slug' => 'giffgaff',
            'provider_code' => 'GG',
            'country_id' => $country->id,
            'is_active' => true,
        ]);
    }
}
