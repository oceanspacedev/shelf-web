<?php

namespace Tests\Feature;

use App\Imports\UserImport;
use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\TalentaUserImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TalentaUserImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('employee_id')->nullable()->unique();
            $table->string('whatsapp_number')->nullable();
            $table->string('whatsapp_login_number', 15)->nullable()->unique();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->timestamps();
        });
        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('job_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function test_talenta_json_creates_user_with_employee_id_and_phone(): void
    {
        $result = (new TalentaUserImportService)->importFromContent(json_encode([
            'data' => [
                'data' => [
                    $this->talentaEmployee([
                        'id_employee' => '2024.08.15.03',
                        'first_name' => 'AAN',
                        'last_name' => 'FEBRIAN',
                        'mobile_phone' => '082180114111',
                        'branch' => 'PT Media Selular Indonesia',
                        'job' => 'MT SALES TECNO',
                        'email' => 'aan@example.test',
                    ]),
                ],
            ],
        ]));

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['created_count']);

        $user = User::query()->first();
        $this->assertSame('AAN FEBRIAN', $user->name);
        $this->assertSame('2024.08.15.03', $user->employee_id);
        $this->assertSame('082180114111', $user->whatsapp_number);
        $this->assertSame('6282180114111', $user->whatsapp_login_number);
        $this->assertSame('aan@example.test', $user->email);
        $this->assertSame('PT Media Selular Indonesia', $user->businessEntity->name);
        $this->assertSame('MT SALES TECNO', $user->jobTitle->title);
    }

    public function test_talenta_json_updates_existing_user_by_employee_id(): void
    {
        $entity = BusinessEntity::create(['name' => 'Lama']);
        $title = JobTitle::create(['title' => 'Staff']);
        $user = User::create([
            'name' => 'AAN FEBRIAN',
            'employee_id' => '2024.08.15.03',
            'business_entity_id' => $entity->id,
            'job_title_id' => $title->id,
        ]);

        $result = (new TalentaUserImportService)->importFromContent(json_encode([
            'data' => [
                'data' => [
                    $this->talentaEmployee([
                        'id_employee' => '2024.08.15.03',
                        'first_name' => 'AAN',
                        'last_name' => 'FEBRIAN PRATAMA',
                        'mobile_phone' => '+6282180114111',
                        'branch' => 'PT Media Selular Indonesia',
                        'job' => 'MT SALES TECNO',
                    ]),
                ],
            ],
        ]));

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['created_count']);
        $this->assertSame(1, $result['success_count']);
        $this->assertSame(1, User::count());

        $user->refresh();
        $this->assertSame('AAN FEBRIAN PRATAMA', $user->name);
        $this->assertSame('+6282180114111', $user->whatsapp_number);
        $this->assertSame('6282180114111', $user->whatsapp_login_number);
        $this->assertSame('PT Media Selular Indonesia', $user->businessEntity->name);
        $this->assertSame('MT SALES TECNO', $user->jobTitle->title);
    }

    public function test_talenta_json_attaches_employee_id_to_legacy_name_match(): void
    {
        $user = User::create(['name' => 'Budi Santoso']);

        $result = (new TalentaUserImportService)->importFromContent(json_encode([
            $this->talentaEmployee([
                'id_employee' => '2019.01.01.01',
                'first_name' => 'Budi',
                'last_name' => 'Santoso',
                'mobile_phone' => '081234567890',
                'branch' => 'CV Top Selular',
                'job' => 'DRIVER',
            ]),
        ]));

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['created_count']);
        $user->refresh();
        $this->assertSame('2019.01.01.01', $user->employee_id);
        $this->assertSame('081234567890', $user->whatsapp_number);
        $this->assertSame('6281234567890', $user->whatsapp_login_number);
    }

    public function test_talenta_json_does_not_steal_another_users_login_number(): void
    {
        User::create([
            'name' => 'Pemegang Login',
            'whatsapp_login_number' => '6281234567890',
        ]);

        $result = (new TalentaUserImportService)->importFromContent(json_encode([
            $this->talentaEmployee([
                'id_employee' => '2020.02.02.02',
                'first_name' => 'Karyawan',
                'last_name' => 'Baru',
                'mobile_phone' => '0812-3456-7890',
                'branch' => 'PT Media',
                'job' => 'STAFF',
            ]),
        ]));

        $this->assertSame(1, $result['error_count']);
        $this->assertSame(0, $result['created_count']);
        $this->assertNull(User::query()->where('employee_id', '2020.02.02.02')->first());
    }

    public function test_excel_import_stores_employee_id_and_phone(): void
    {
        $import = new UserImport;
        $import->collection(Collection::make([
            collect(['Nama', 'Badan Usaha', 'Jabatan', 'Employee ID', 'No. HP']),
            collect(['Siti Aminah', 'CV Complete Selular', 'FRONTLINER', '2023.11.01.03', '081222295165']),
        ]));

        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->assertSame('Siti Aminah', $user->name);
        $this->assertSame('2023.11.01.03', $user->employee_id);
        $this->assertSame('081222295165', $user->whatsapp_number);
        $this->assertSame('6281222295165', $user->whatsapp_login_number);
        $this->assertSame('CV Complete Selular', $user->businessEntity->name);
        $this->assertSame('FRONTLINER', $user->jobTitle->title);
    }

    public function test_excel_import_updates_phone_for_existing_employee_id(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Complete Selular']);
        $title = JobTitle::create(['title' => 'FRONTLINER']);
        User::create([
            'name' => 'Siti Aminah',
            'employee_id' => '2023.11.01.03',
            'business_entity_id' => $entity->id,
            'job_title_id' => $title->id,
        ]);

        $import = new UserImport;
        $import->collection(Collection::make([
            collect(['Siti Aminah', 'CV Complete Selular', 'FRONTLINER', '2023.11.01.03', '081999888777']),
        ]));

        $this->assertSame(1, User::count());
        $user = User::query()->first();
        $this->assertSame('081999888777', $user->whatsapp_number);
        $this->assertSame('6281999888777', $user->whatsapp_login_number);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function talentaEmployee(array $overrides): array
    {
        return array_merge([
            'branch' => 'PT Media Selular Indonesia',
            'email' => null,
            'mobile_phone' => null,
            'phone' => null,
            'id' => '1',
            'id_employee' => '2024.01.01.01',
            'job' => 'STAFF',
            'title' => 'STAFF',
            'first_name' => 'NAMA',
            'last_name' => '',
        ], $overrides);
    }
}
