<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademicTermRequest;
use App\Http\Requests\UpdateAcademicTermRequest;
use App\Http\Requests\UpdateGradingDeadlinesRequest;
use App\Http\Resources\AcademicTermResource;
use App\Models\AcademicTerm;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AcademicTermController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', AcademicTerm::class);

        $perPage = $request->query('per_page', 15);

        $query = AcademicTerm::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where('academic_year', 'like', "%{$search}%");
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('academic_year'), fn ($q, $year) => $q->where('academic_year', $year))
            ->when($request->query('term'), fn ($q, $term) => $q->where('term', $term));

        $academicTerms = $this->applySorting($query, $request)->paginate($perPage);

        return AcademicTermResource::collection($academicTerms)->additional([
            'success' => true,
            'message' => 'Academic terms retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAcademicTermRequest $request)
    {
        $academicTerm = AcademicTerm::create($request->validated())->refresh();

        return AcademicTermResource::make($academicTerm)->additional([
            'success' => true,
            'message' => 'Academic term created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicTerm $academicTerm)
    {
        Gate::authorize('view', $academicTerm);

        return AcademicTermResource::make($academicTerm)->additional([
            'success' => true,
            'message' => 'Academic term retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAcademicTermRequest $request, AcademicTerm $academicTerm)
    {
        $academicTerm->update($request->validated());

        return AcademicTermResource::make($academicTerm)->additional([
            'success' => true,
            'message' => 'Academic term updated successfully.',
        ]);
    }

    /**
     * Update the grading deadlines of the specified resource.
     */
    public function updateDeadlines(UpdateGradingDeadlinesRequest $request, AcademicTerm $academicTerm)
    {
        $academicTerm->update($request->validated());

        return AcademicTermResource::make($academicTerm)->additional([
            'success' => true,
            'message' => 'Academic term grading deadlines updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicTerm $academicTerm): Response
    {
        if ($academicTerm->courseOfferings()->exists()) {
            abort(409, 'Academic term cannot be deleted because it still has course offerings.');
        }

        $academicTerm->delete();

        return response()->noContent();
    }
}
