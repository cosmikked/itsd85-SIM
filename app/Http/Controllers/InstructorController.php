<?php

namespace App\Http\Controllers;

use App\Http\Resources\InstructorResource;
use App\Models\User;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InstructorController extends Controller
{
    use Sortable;

    /**
     * Read-only lookup of instructor accounts for staff.
     * /users stays administrator-only, so registrars cannot manage accounts.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewInstructors', User::class);

        $perPage = $request->query('per_page', 15);

        $query = User::query()
            ->where('role', 'instructor')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $instructors = $this->applySorting($query, $request)->paginate($perPage);

        return InstructorResource::collection($instructors)->additional([
            'success' => true,
            'message' => 'Instructors retrieved successfully.',
        ]);
    }
}
