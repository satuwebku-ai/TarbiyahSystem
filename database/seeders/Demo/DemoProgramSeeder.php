<?php

namespace Database\Seeders\Demo;

use App\Helpers\AttendanceClock;
use App\Models\ActivityApproval;
use App\Models\ActivityBudgetItem;
use App\Models\ActivityParticipant;
use App\Models\ActivityReport;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Models\WorkActivity;
use App\Models\WorkProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Program kerja dan kegiatan contoh pada setiap tingkat organisasi, dengan semua status alur persetujuan
 * (draft, menunggu persetujuan, disetujui, perlu revisi), kegiatan yang sudah selesai (lengkap dengan laporan),
 * sedang berjalan, dan akan datang, plus anggaran dan peserta.
 *
 * Tanggal kegiatan dihitung dari hari pertama seeder dijalankan, lalu tidak diubah lagi (aman diulang).
 */
class DemoProgramSeeder extends Seeder
{
    use DemoDataset;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->ensure(Admin::where('username', 'superadmin')->exists(), DemoAkunSeeder::class);

        DB::transaction(function () {
            $super = Admin::where('username', 'superadmin')->value('id');
            $pb = Admin::where('username', 'pb')->value('id');

            // Pengurus Besar (disetujui Super Admin).
            $this->program(self::PB_CODE, 'DEMO-PB-01', 'Penguatan Mutu Pendidikan Yayasan', 'approved', 250_000_000, $pb, $super, [
                ['01', 'Rapat Kerja Tahunan Yayasan', 'Aula Pengurus Besar', 'completed', -40, 1, 'reviewed', [['Konsumsi', 12_000_000, 11_400_000], ['Akomodasi peserta', 30_000_000, 28_750_000]]],
                ['02', 'Workshop Kurikulum Lintas Jenjang', 'Hotel Mitra, Palu', 'in_progress', -2, 5, null, [['Narasumber', 15_000_000, 5_000_000], ['Bahan workshop', 8_000_000, 2_100_000]]],
                ['03', 'Evaluasi Tengah Tahun', 'Aula Pengurus Besar', 'planned', 21, 1, null, [['Konsumsi', 10_000_000, 0]]],
            ], [self::PB_CODE]);

            // Setiap KOMWIL (disetujui Pengurus Besar).
            $komdaIndex = 0;
            foreach ($this->provinces() as [$provCode, $provName, $komdas]) {
                $komwilAdmin = Admin::where('username', $this->usernameFor('komwil', $provCode))->value('id');
                $this->program("KOMWIL-{$provCode}", "DEMO-KW-{$provCode}", "Pembinaan Sekolah Wilayah {$provName}", 'approved', 90_000_000, $komwilAdmin, $pb, [
                    ['01', 'Supervisi Sekolah Semester Ganjil', 'Seluruh sekolah wilayah', 'completed', -25, 3, 'submitted', [['Transportasi tim supervisi', 6_000_000, 5_800_000]]],
                    ['02', 'Pelatihan Guru Wilayah', 'Gedung pertemuan wilayah', 'planned', 14, 2, null, [['Materi pelatihan', 7_500_000, 0], ['Konsumsi', 9_000_000, 0]]],
                ], ["KOMWIL-{$provCode}"]);

                // Setiap KOMDA: status program bergilir supaya semua tahap alur persetujuan terlihat.
                $statuses = ['approved', 'submitted', 'draft', 'rejected'];
                foreach ($komdas as [$komdaCode, $komdaName]) {
                    $unitCode = "KOMDA-{$provCode}-{$komdaCode}";
                    $status = $statuses[$komdaIndex++ % 4];
                    $admin = Admin::where('username', $this->usernameFor('komda', "{$provCode}-{$komdaCode}"))->value('id');
                    $schools = collect($this->schoolSpecs())->where('komda_code', $unitCode)->pluck('code')->all();

                    $activities = $status === 'approved' ? [
                        ['01', 'Bimbingan Teknis Kepala Sekolah', 'Kantor KOMDA', 'completed', -12, 1, 'draft', [['Konsumsi', 3_000_000, 2_850_000]]],
                        ['02', 'Lomba Sains Antar Sekolah', $komdaName, 'planned', 28, 2, null, [['Hadiah dan piala', 6_000_000, 0], ['Panitia', 2_500_000, 0]]],
                    ] : [
                        ['01', 'Pendataan Sarana Sekolah', $komdaName, 'planned', 35, 3, null, [['Transportasi', 2_000_000, 0]]],
                    ];

                    $this->program($unitCode, "DEMO-KD-{$provCode}-{$komdaCode}", "Peningkatan Mutu Sekolah {$komdaName}", $status, 40_000_000, $admin, $status === 'approved' ? $komwilAdmin : null, $activities,
                        array_map(fn ($c) => DemoKepegawaianSeeder::number($c, 1), $schools), true);
                }
            }
        });

        $this->command?->info('Program kerja: '.WorkProgram::count().', kegiatan: '.WorkActivity::count().', laporan: '.ActivityReport::count().'.');
    }

    /**
     * @param array<int, array{0:string,1:string,2:string,3:string,4:int,5:int,6:?string,7:array}> $activities
     *        [kode, judul, lokasi, status, hari mulai (relatif hari ini), lama (hari), status laporan, [[butir anggaran, rencana, realisasi]]]
     * @param array<int, string> $participants kode unit (untuk pengurus) atau nomor pegawai (bila $byNumber)
     */
    private function program(string $unitCode, string $code, string $title, string $status, int $budget, ?int $creator, ?int $approver, array $activities, array $participants, bool $byNumber = false): void
    {
        $unit = OrganizationalUnit::where('code', $unitCode)->firstOrFail();
        $today = AttendanceClock::today();

        $program = WorkProgram::firstOrCreate(['organizational_unit_id' => $unit->id, 'code' => $code], [
            'title' => $title,
            'description' => 'Program contoh (data demo).',
            'objectives' => 'Meningkatkan mutu layanan pendidikan dan tata kelola di lingkup unit.',
            'start_date' => self::YEAR_START, 'end_date' => self::YEAR_END, 'estimated_budget' => $budget, 'status' => $status,
            'submitted_at' => in_array($status, ['submitted', 'approved', 'rejected'], true) ? $today->copy()->subDays(30) : null,
            'approved_at' => $status === 'approved' ? $today->copy()->subDays(28) : null,
            'rejected_at' => $status === 'rejected' ? $today->copy()->subDays(7) : null,
            'created_by_admin_id' => $creator, 'approved_by_admin_id' => $status === 'approved' ? $approver : null,
        ]);

        if (! $program->wasRecentlyCreated) {
            return;
        }

        // Riwayat persetujuan program.
        if ($status !== 'draft') {
            ActivityApproval::create([
                'work_program_id' => $program->id, 'work_activity_id' => null,
                'status' => match ($status) {'submitted' => 'pending', 'rejected' => 'rejected', default => 'approved'},
                'note' => $status === 'rejected' ? 'Rincian anggaran belum sesuai, mohon direvisi.' : null,
                'requested_by_admin_id' => $creator,
                'reviewed_by_admin_id' => $status === 'submitted' ? null : $approver,
                'requested_at' => $program->submitted_at, 'reviewed_at' => $status === 'submitted' ? null : ($program->approved_at ?? $program->rejected_at),
            ]);
        }

        foreach ($activities as [$n, $activityTitle, $location, $activityStatus, $offset, $length, $reportStatus, $budgetItems]) {
            $start = $today->copy()->addDays($offset)->setTime(8, 0);
            $end = $start->copy()->addDays($length - 1)->setTime(15, 0);
            $done = $activityStatus === 'completed';
            $running = $activityStatus === 'in_progress';

            $activity = WorkActivity::create([
                'work_program_id' => $program->id, 'organizational_unit_id' => $unit->id, 'code' => "K-{$n}", 'title' => $activityTitle,
                'description' => 'Kegiatan contoh (data demo).', 'location' => $location,
                'planned_start_at' => $start, 'planned_end_at' => $end,
                'actual_start_at' => $done || $running ? $start : null, 'actual_end_at' => $done ? $end : null,
                'status' => $activityStatus, 'created_by_admin_id' => $creator,
            ]);

            foreach ($budgetItems as [$name, $planned, $realized]) {
                ActivityBudgetItem::create(['work_activity_id' => $activity->id, 'name' => $name, 'planned_amount' => $planned, 'realized_amount' => $realized]);
            }

            foreach ($this->participants($participants, $byNumber) as $employee) {
                ActivityParticipant::create([
                    'work_activity_id' => $activity->id, 'employee_id' => $employee->id, 'participant_name' => $employee->name,
                    'role' => 'Peserta', 'attendance_status' => $done ? 'present' : 'planned',
                ]);
            }

            if ($done && $reportStatus) {
                $reviewed = $reportStatus === 'reviewed';
                ActivityReport::create([
                    'work_activity_id' => $activity->id,
                    'summary' => 'Kegiatan terlaksana sesuai rencana dengan peserta yang hadir lengkap.',
                    'outcomes' => 'Terbentuk kesepahaman dan rencana tindak lanjut bersama.',
                    'challenges' => 'Jadwal peserta dari beberapa daerah sempat bergeser.',
                    'recommendations' => 'Jadwalkan kegiatan serupa lebih awal pada semester berikutnya.',
                    'status' => $reportStatus,
                    'submitted_at' => $reportStatus === 'draft' ? null : $end->copy()->addDay(),
                    'submitted_by_admin_id' => $reportStatus === 'draft' ? null : $creator,
                    'evaluated_by_admin_id' => $reviewed ? $program->approved_by_admin_id : null,
                    'evaluated_at' => $reviewed ? $end->copy()->addDays(3) : null,
                    'evaluation_note' => $reviewed ? 'Laporan lengkap dan sesuai tujuan kegiatan.' : null,
                ]);
            }
        }
    }

    /** Pegawai peserta: pengurus unit (kode unit) atau kepala sekolah (nomor pegawai), maksimal 4 orang. */
    private function participants(array $keys, bool $byNumber)
    {
        if ($byNumber) {
            return Employee::whereIn('employee_number', $keys)->orderBy('id')->limit(4)->get(['id', 'name']);
        }

        return Employee::whereHas('assignments', fn ($q) => $q->whereHas('unit', fn ($u) => $u->whereIn('code', $keys)))
            ->orderBy('id')->limit(4)->get(['id', 'name']);
    }
}
