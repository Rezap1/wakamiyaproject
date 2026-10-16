<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgendaManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wms_runtime.connection', 'sqlite');
        config()->set('wms_runtime.database', ':memory:');

        Schema::create('MASTER_ROLE', function (Blueprint $table): void {
            $table->string('Role_ID')->primary();
            $table->string('Role_Name');
            $table->boolean('Is_Active')->nullable();
        });
        Schema::create('MASTER_USER', function (Blueprint $table): void {
            $table->string('User_ID')->primary();
            $table->string('Username')->nullable();
            $table->string('Full_Name')->nullable();
            $table->string('Role_ID');
            $table->string('Is_Active')->nullable();
        });
        Schema::create('WMS_ID_SEQUENCE', function (Blueprint $table): void {
            $table->string('Sequence_Key')->primary();
            $table->unsignedBigInteger('Last_Value')->default(0);
            $table->dateTime('Updated_At')->nullable();
        });

        $roles = ['ADMINISTRATOR', 'HR', 'ACADEMIC', 'MARKETING', 'FINANCE', 'DIRECTOR', 'TEACHER', 'EMPLOYEE', 'STUDENT'];
        foreach ($roles as $role) {
            DB::table('MASTER_ROLE')->insert([
                'Role_ID' => 'ROLE-'.$role,
                'Role_Name' => $role,
                'Is_Active' => true,
            ]);
            DB::table('MASTER_USER')->insert([
                'User_ID' => 'USR-'.$role,
                'Username' => strtolower($role),
                'Full_Name' => 'Pengguna '.$role,
                'Role_ID' => 'ROLE-'.$role,
                'Is_Active' => 'TRUE',
            ]);
        }
        DB::table('MASTER_USER')->insert([
            'User_ID' => 'USR-MASTER',
            'Username' => 'master',
            'Full_Name' => 'Master Administrator',
            'Role_ID' => 'MASTER',
            'Is_Active' => 'TRUE',
        ]);

        (require database_path('migrations/wms_mysql/2026_10_16_000001_create_wms_agenda_table.php'))->up();
    }

    #[DataProvider('nonStudentRoles')]
    public function test_every_non_student_role_can_create_agenda(string $role): void
    {
        $this->actingAs($this->user($role));

        $response = $this->post(route('agendas.store'), $this->payload([
            'PIC_User_ID' => 'USR-'.$role,
            'Purpose' => 'Agenda milik '.$role,
        ]));

        $response->assertRedirect(route('agendas.show', 'AGD000001'));
        $this->assertDatabaseHas('WMS_AGENDA', [
            'Agenda_ID' => 'AGD000001',
            'PIC_User_ID' => 'USR-'.$role,
            'Purpose' => 'Agenda milik '.$role,
            'Created_By' => 'USR-'.$role,
        ]);
    }

    public static function nonStudentRoles(): array
    {
        return [
            'master' => ['MASTER'],
            'administrator' => ['ADMINISTRATOR'],
            'hr' => ['HR'],
            'academic' => ['ACADEMIC'],
            'marketing' => ['MARKETING'],
            'finance' => ['FINANCE'],
            'director' => ['DIRECTOR'],
            'teacher' => ['TEACHER'],
            'employee' => ['EMPLOYEE'],
        ];
    }

    public function test_student_cannot_access_any_agenda_endpoint_including_direct_backend_requests(): void
    {
        $this->seedAgenda();
        $this->actingAs($this->user('STUDENT'));

        $this->get(route('agendas.index'))->assertForbidden();
        $this->get(route('agendas.create'))->assertForbidden();
        $this->post(route('agendas.store'), $this->payload())->assertForbidden();
        $this->get(route('agendas.show', 'AGD000099'))->assertForbidden();
        $this->get(route('agendas.edit', 'AGD000099'))->assertForbidden();
        $this->put(route('agendas.update', 'AGD000099'), $this->payload())->assertForbidden();
        $this->delete(route('agendas.destroy', 'AGD000099'))->assertForbidden();

        $this->assertDatabaseCount('WMS_AGENDA', 1);
    }

    public function test_sidebar_menu_is_visible_only_for_authorized_roles(): void
    {
        $this->actingAs($this->user('HR'));
        $authorized = Blade::render('<x-dashboard.sidebar user-role="HR" />');
        $this->assertStringContainsString(route('agendas.index'), $authorized);
        $this->assertStringContainsString('Agenda Management', $authorized);

        $this->actingAs($this->user('STUDENT'));
        $student = Blade::render('<x-dashboard.sidebar user-role="STUDENT" />');
        $this->assertStringNotContainsString(route('agendas.index'), $student);
        $this->assertStringNotContainsString('Agenda Management', $student);
    }

    public function test_crud_status_results_follow_up_overdue_display_and_soft_delete(): void
    {
        $this->actingAs($this->user('ADMINISTRATOR'));

        $this->post(route('agendas.store'), $this->payload())
            ->assertRedirect(route('agendas.show', 'AGD000001'));
        $this->get(route('agendas.show', 'AGD000001'))
            ->assertOk()
            ->assertSee('Rapat koordinasi awal');

        $updated = $this->payload([
            'Status' => 'COMPLETED',
            'Activity_Result' => 'Keputusan kegiatan telah disepakati.',
            'Follow_Up' => 'Kirim notulen final.',
            'Follow_Up_Deadline' => '2020-01-01',
            'Follow_Up_Status' => 'IN_PROGRESS',
            'Document_Notes' => 'https://example.test/notulen',
        ]);
        $this->put(route('agendas.update', 'AGD000001'), $updated)
            ->assertRedirect(route('agendas.show', 'AGD000001'));

        $this->assertDatabaseHas('WMS_AGENDA', [
            'Agenda_ID' => 'AGD000001',
            'Status' => 'COMPLETED',
            'Activity_Result' => 'Keputusan kegiatan telah disepakati.',
            'Follow_Up_Status' => 'IN_PROGRESS',
            'Follow_Up_Deadline' => '2020-01-01',
        ]);
        $this->get(route('agendas.index'))
            ->assertOk()
            ->assertSee('TERLEWAT');

        $this->delete(route('agendas.destroy', 'AGD000001'))
            ->assertRedirect(route('agendas.index'));
        $this->assertNotNull(DB::table('WMS_AGENDA')->where('Agenda_ID', 'AGD000001')->value('Deleted_At'));
        $this->get(route('agendas.show', 'AGD000001'))->assertNotFound();
    }

    public function test_search_and_all_required_filters_limit_agenda_list(): void
    {
        $this->seedAgenda();
        $this->seedAgenda([
            'Agenda_ID' => 'AGD000100',
            'Agenda_Date' => '2026-11-20',
            'Agenda_Type' => 'VISIT',
            'PIC_User_ID' => 'USR-HR',
            'Status' => 'IN_PROGRESS',
            'Follow_Up_Status' => 'COMPLETED',
            'Purpose' => 'Kunjungan perusahaan Sakura unik',
        ]);
        $this->actingAs($this->user('MARKETING'));

        $response = $this->get(route('agendas.index', [
            'search' => 'Sakura unik',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'type' => 'VISIT',
            'pic' => 'USR-HR',
            'agenda_status' => 'IN_PROGRESS',
            'follow_up_status' => 'COMPLETED',
        ]));

        $response->assertOk()
            ->assertSee('AGD000100')
            ->assertDontSee('AGD000099');
    }

    public function test_date_filters_can_be_used_independently(): void
    {
        $this->seedAgenda();
        $this->actingAs($this->user('ACADEMIC'));

        $this->get(route('agendas.index', ['date_to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('AGD000099');
        $this->get(route('agendas.index', ['date_from' => '2026-10-01']))
            ->assertOk()
            ->assertSee('AGD000099');
    }

    public function test_end_time_must_be_after_start_time_and_invalid_data_is_not_written(): void
    {
        $this->actingAs($this->user('TEACHER'));

        $this->from(route('agendas.create'))->post(route('agendas.store'), $this->payload([
            'Start_Time' => '14:00',
            'End_Time' => '13:59',
        ]))
            ->assertRedirect(route('agendas.create'))
            ->assertSessionHasErrors('End_Time');

        $this->assertDatabaseCount('WMS_AGENDA', 0);
    }

    public function test_student_cannot_be_forged_as_pic(): void
    {
        $this->actingAs($this->user('HR'));

        $this->from(route('agendas.create'))->post(route('agendas.store'), $this->payload([
            'PIC_User_ID' => 'USR-STUDENT',
        ]))
            ->assertRedirect(route('agendas.create'))
            ->assertSessionHasErrors('PIC_User_ID');

        $this->assertDatabaseCount('WMS_AGENDA', 0);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'Agenda_Date' => '2026-10-20',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Agenda_Type' => 'MEETING',
            'Related_Parties' => 'Tim internal dan tamu perusahaan',
            'Purpose' => 'Rapat koordinasi awal',
            'PIC_User_ID' => 'USR-ADMINISTRATOR',
            'Status' => 'SCHEDULED',
            'Activity_Result' => '',
            'Follow_Up' => '',
            'Follow_Up_Deadline' => '',
            'Follow_Up_Status' => 'NOT_STARTED',
            'Document_Notes' => '',
        ], $overrides);
    }

    private function seedAgenda(array $overrides = []): void
    {
        DB::table('WMS_AGENDA')->insert(array_merge([
            'Agenda_ID' => 'AGD000099',
            'Agenda_Date' => '2026-10-20',
            'Start_Time' => '09:00:00',
            'End_Time' => '10:00:00',
            'Agenda_Type' => 'MEETING',
            'Related_Parties' => 'Tim internal',
            'Purpose' => 'Agenda pembanding',
            'PIC_User_ID' => 'USR-ADMINISTRATOR',
            'Status' => 'SCHEDULED',
            'Activity_Result' => null,
            'Follow_Up' => null,
            'Follow_Up_Deadline' => null,
            'Follow_Up_Status' => 'NOT_STARTED',
            'Document_Notes' => null,
            'Created_By' => 'USR-ADMINISTRATOR',
            'Created_At' => '2026-10-01 00:00:00',
            'Updated_By' => 'USR-ADMINISTRATOR',
            'Updated_At' => '2026-10-01 00:00:00',
            'Deleted_At' => null,
        ], $overrides));
    }

    private function user(string $role): GenericUser
    {
        return new GenericUser([
            'id' => 'USR-'.$role,
            'User_ID' => 'USR-'.$role,
            'Role_ID' => $role === 'MASTER' ? 'MASTER' : 'ROLE-'.$role,
            'Role' => $role,
            'Full_Name' => 'Pengguna '.$role,
        ]);
    }
}
