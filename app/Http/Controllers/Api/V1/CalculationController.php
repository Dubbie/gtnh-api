<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CalculationException;
use App\Exceptions\ItemNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateRequirementsRequest;
use App\Services\CalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

class CalculationController extends Controller
{
    protected CalculationService $calculationService;

    public function __construct(CalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Calculate
     *
     * Calculates the required items and quantities for a given item and quantity.
     * @unauthenticated
     */
    public function __invoke(CalculateRequirementsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user('sanctum');

        try {
            $result = $this->calculationService->calculateRequirements(
                $validated['item_id'],
                (float) $validated['quantity'],
                $user
            );

            return response()->json($result);
        } catch (ItemNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (CalculationException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (Throwable $e) {
            return response()->json(['message' => 'An unexpected server error occurred.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
