<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Traits\Sortable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoomController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the rooms (read-only lookup for building schedules).
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Room::class);

        $perPage = $request->query('per_page', 15);

        $query = Room::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('building', 'like', "%{$search}%");
                });
            })
            ->when($request->query('building'), fn ($q, $building) => $q->where('building', $building));

        $rooms = $this->applySorting($query, $request)->paginate($perPage);

        return RoomResource::collection($rooms)->additional([
            'success' => true,
            'message' => 'Rooms retrieved successfully.',
        ]);
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room)
    {
        Gate::authorize('view', $room);

        return RoomResource::make($room)->additional([
            'success' => true,
            'message' => 'Room retrieved successfully.',
        ]);
    }
}
