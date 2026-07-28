<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewSessionRequest;
use App\Http\Requests\Api\V1\UpdateReviewSessionRequest;
use App\Http\Resources\Api\V1\ReviewSessionResource;
use App\Models\ReviewSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewSessionController extends Controller
{
    public function index(Request $request)
    {
        $query = ReviewSession::query()->where('user_id', Auth::id());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return ReviewSessionResource::collection(
            $query->orderByDesc('started_at')->get()
        );
    }

    public function store(StoreReviewSessionRequest $request)
    {
        $session = ReviewSession::create([
            'user_id' => Auth::id(),
            'session_type' => $request->validated('session_type'),
            'started_at' => now(),
            'status' => 'in_progress',
            'total_ayat_reviewed' => 0,
        ]);

        return ReviewSessionResource::make($session)->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        $session = ReviewSession::where('user_id', Auth::id())->findOrFail($id);

        return ReviewSessionResource::make($session);
    }

    public function update(UpdateReviewSessionRequest $request, int $id)
    {
        $session = ReviewSession::where('user_id', Auth::id())->findOrFail($id);

        $session->update([
            'status' => $request->validated('status'),
            'ended_at' => now(),
        ]);

        return ReviewSessionResource::make($session);
    }
}
