<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Admin;
use App\Models\Classroom;
use App\Models\School;
use App\Models\StudentClassPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentAttendanceAccess
{
    public function __construct(private OrganizationAccess $organization) {}

    public function schools(Admin $admin): Builder
    {
        $visible = $this->organization->visibleUnitIds($admin);

        return School::query()
            ->when($visible !== null, fn (Builder $query) => $query->whereIn('organizational_unit_id', $visible));
    }

    public function academicYears(Admin $admin): Builder
    {
        return AcademicYear::query()->whereHas(
            'school',
            fn (Builder $query) => $this->applySchoolScope($query, $admin)
        );
    }

    public function classrooms(Admin $admin): Builder
    {
        return Classroom::query()->whereHas(
            'school',
            fn (Builder $query) => $this->applySchoolScope($query, $admin)
        );
    }

    public function applySchoolScope(Builder $query, Admin $admin): Builder
    {
        $visible = $this->organization->visibleUnitIds($admin);

        return $query->when($visible !== null, fn (Builder $builder) => $builder->whereIn('organizational_unit_id', $visible));
    }

    public function findClassroom(Admin $admin, int $classroomId): Classroom
    {
        return $this->classrooms($admin)
            ->with(['school.unit', 'academicYear'])
            ->findOrFail($classroomId);
    }

    /**
     * Return students whose enrollment and class placement cover this date.
     * This uses the placement date range rather than its current status so past
     * attendance can still be viewed after a student changes classes.
     */
    public function roster(Classroom $classroom, string $date): Collection
    {
        return StudentClassPlacement::query()
            ->with(['enrollment.student'])
            ->where('classroom_id', $classroom->id)
            ->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $date))
            ->whereHas('enrollment', function (Builder $query) use ($classroom, $date) {
                $query->where('academic_year_id', $classroom->academic_year_id)
                    ->whereDate('enrollment_date', '<=', $date)
                    ->whereIn('status', ['active', 'completed', 'transferred'])
                    ->whereHas('student', fn (Builder $student) => $student
                        ->where('school_id', $classroom->school_id)
                        ->where('status', 'active'));
            })
            ->get()
            ->map(fn (StudentClassPlacement $placement) => $placement->enrollment?->student)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
