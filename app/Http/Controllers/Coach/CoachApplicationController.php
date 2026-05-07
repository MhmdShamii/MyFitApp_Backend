<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\RejectCoachApplicationRequest;
use App\Http\Requests\Coach\SubmitCoachApplicationRequest;
use App\Http\Resources\Coach\CoachApplicationResource;
use App\Http\Responses\ApiResponse;
use App\Models\CoachApplication;
use App\Services\Coach\CoachApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(private CoachApplicationService $coachApplicationService) {}

    public function show(Request $request): JsonResponse
    {
        $application = $this->coachApplicationService->getApplication($request->user());

        if (!$application) {
            return $this->error('No application found.', 404);
        }

        return $this->success(new CoachApplicationResource($application), dataKey: 'application');
    }

    public function store(SubmitCoachApplicationRequest $request): JsonResponse
    {
        $application = $this->coachApplicationService->submit(
            $request->user(),
            $request->validated('description'),
            $request->file('documents'),
        );

        return $this->success(new CoachApplicationResource($application), 'Application submitted successfully.', 'application', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->coachApplicationService->getAll($request->query('status'));

        return $this->paginated(
            CoachApplicationResource::collection($paginator->items()),
            [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more'    => $paginator->hasMorePages(),
            ],
            'Applications fetched successfully.'
        );
    }

    public function approve(Request $request, CoachApplication $coachApplication): JsonResponse
    {
        $application = $this->coachApplicationService->approve($coachApplication, $request->user());

        return $this->success(new CoachApplicationResource($application), 'Application approved.', 'application');
    }

    public function reject(RejectCoachApplicationRequest $request, CoachApplication $coachApplication): JsonResponse
    {
        $application = $this->coachApplicationService->reject(
            $coachApplication,
            $request->user(),
            $request->validated('reason')
        );

        return $this->success(new CoachApplicationResource($application), 'Application rejected.', 'application');
    }
}
