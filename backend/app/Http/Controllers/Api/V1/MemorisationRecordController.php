<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemorisationClassification;
use App\Enums\ReviewIntervalStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMemorisationRecordRequest;
use App\Http\Requests\Api\V1\UpdateMemorisationRecordRequest;
use App\Http\Resources\Api\V1\MemorisationRecordResource;
use App\Models\MemorisationRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemorisationRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = MemorisationRecord::query()
            ->where('user_id', Auth::id())
            ->with('ayah.surah');

        if ($request->boolean('due')) {
            $query->whereDate('next_review_date', '<=', now()->toDateString());
        }

        if ($request->filled('classification')) {
            $query->where('classification', MemorisationClassification::from($request->string('classification')->toString()));
        }

        $records = $query->orderBy('next_review_date')->get();

        return MemorisationRecordResource::collection($records);
    }

    public function store(StoreMemorisationRecordRequest $request)
    {
        $record = MemorisationRecord::firstOrCreate(
            ['user_id' => Auth::id(), 'ayah_id' => $request->validated('ayah_id')],
            [
                'memory_strength_score' => 0,
                'recall_count' => 0,
                'mistake_count' => 0,
                'current_interval_stage' => ReviewIntervalStage::Immediate,
                'classification' => MemorisationClassification::Sabak,
                'next_review_date' => now()->toDateString(),
            ]
        );

        return MemorisationRecordResource::make($record->load('ayah.surah'))
            ->response()
            ->setStatusCode($record->wasRecentlyCreated ? 201 : 200);
    }

    public function show(int $id)
    {
        $record = MemorisationRecord::where('user_id', Auth::id())->with('ayah.surah')->findOrFail($id);

        return MemorisationRecordResource::make($record);
    }

    public function update(UpdateMemorisationRecordRequest $request, int $id)
    {
        $record = MemorisationRecord::where('user_id', Auth::id())->findOrFail($id);

        $record->update(['next_review_date' => now()->toDateString()]);

        return MemorisationRecordResource::make($record->load('ayah.surah'));
    }
}
