<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\Response;

class ProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $programs = Program::paginate(15);

        return ProgramResource::collection($programs)->additional([
            'success' => true,
            'message' => 'Programs retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProgramRequest $request)
    {
        $program = Program::create($request->validated());

        return ProgramResource::make($program)->additional([
            'success' => true,
            'message' => 'Program created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Program $program)
    {
        return ProgramResource::make($program)->additional([
            'success' => true,
            'message' => 'Program retrieved successfully.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProgramRequest $request, Program $program)
    {
        $program->update($request->validated());

        return ProgramResource::make($program)->additional([
            'success' => true,
            'message' => 'Program updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program): Response
    {
        if ($program->students()->exists()) {
            abort(409, 'Program cannot be deleted because it still has students.');
        }

        $program->delete();

        return response()->noContent();
    }
}
