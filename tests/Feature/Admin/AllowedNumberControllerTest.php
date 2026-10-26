<?php

namespace Tests\Feature\Admin;

use App\Models\AllowedNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AllowedNumberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_upload_preserves_long_serial_values(): void
    {
        Storage::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $path = tempnam(sys_get_temp_dir(), 'allowed-numbers-');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['mobile', 'serial', 'provider', 'country'],
            ['447700900123', null, 'Test Provider', 'GB'],
            ['447700900124', null, 'Test Provider', 'GB'],
        ]);
        $sheet->setCellValueExplicit('B2', '862347823234823479', DataType::TYPE_NUMERIC);
        $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('0.00E+00');
        $sheet->setCellValueExplicit('B3', '862347823234823480', DataType::TYPE_STRING);

        try {
            (new Xlsx($spreadsheet))->save($path);
            $contents = file_get_contents($path);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($path);
        }

        $response = $this->actingAs($admin)->post('/admin/allowed-numbers/import-file', [
            'file' => File::createWithContent('allowed_numbers.xlsx', $contents),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('allowed_numbers', [
            'mobile' => '447700900123',
            'serial' => '862347823234823479',
        ]);
        $this->assertDatabaseHas('allowed_numbers', [
            'mobile' => '447700900124',
            'serial' => '862347823234823480',
        ]);
        $this->assertDatabaseHas('allowed_number_files', [
            'name' => 'allowed_numbers.xlsx',
            'imported_rows' => 2,
        ]);
        $this->assertSame(2, AllowedNumber::count());
    }

    public function test_csv_upload_still_imports_numbers(): void
    {
        Storage::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/admin/allowed-numbers/import-file', [
            'file' => File::createWithContent(
                'allowed_numbers.csv',
                "mobile,serial,provider,country\n447700900125,829953289034771926,Test Provider,GB\n"
            ),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('allowed_numbers', [
            'mobile' => '447700900125',
            'serial' => '829953289034771926',
        ]);
    }
}
