<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademicTermRequest;
use App\Http\Requests\UpdateAcademicTermRequest;
use App\Http\Resources\AcademicTermResource;
use App\Models\AcademicTerm;
use Illuminate\Http\Response;

class AcademicTermController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $academicTerms = AcademicTerm::paginate(15);

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
