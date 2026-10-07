<?php

namespace App\Models;

use Database\Factories\CourseOfferingScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOfferingSchedule extends Model
{
    /** @use HasFactory<CourseOfferingScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'course_offering_id',
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
