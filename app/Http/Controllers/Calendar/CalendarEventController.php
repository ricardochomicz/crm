<?php

namespace App\Http\Controllers\Calendar;

use App\Actions\Calendar\CalendarEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\CalendarEventRequest;
use App\Http\Resources\Calendar\CalendarEventResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CalendarEventController extends Controller
{
    public function assignees(CalendarEventRequest $request, string $tenantId, CalendarEventAction $action): array
    {
        return ['data' => $action->assignableMembers($request->attributes->get('active_tenant'), $request->user())];
    }

    public function index(CalendarEventRequest $request, string $tenantId, CalendarEventAction $action): AnonymousResourceCollection
    {
        return CalendarEventResource::collection($action->list($request->attributes->get('active_tenant'), $request->user(), $request->validated()));
    }

    public function activities(CalendarEventRequest $request, string $tenantId, CalendarEventAction $action): AnonymousResourceCollection
    {
        return CalendarEventResource::collection($action->activities($request->attributes->get('active_tenant'), $request->user(), $request->validated()));
    }

    public function store(CalendarEventRequest $request, string $tenantId, CalendarEventAction $action): CalendarEventResource
    {
        return new CalendarEventResource($action->create($request->attributes->get('active_tenant'), $request->user(), $request->validated()));
    }

    public function update(CalendarEventRequest $request, string $tenantId, string $eventId, CalendarEventAction $action): CalendarEventResource
    {
        return new CalendarEventResource($action->update($request->attributes->get('active_tenant'), $request->user(), $eventId, $request->validated()));
    }

    public function history(CalendarEventRequest $request, string $tenantId, string $eventId, CalendarEventAction $action): array
    {
        return ['data' => $action->history($request->attributes->get('active_tenant'), $request->user(), $eventId)];
    }

    public function destroy(CalendarEventRequest $request, string $tenantId, string $eventId, CalendarEventAction $action): Response
    {
        $action->delete($request->attributes->get('active_tenant'), $request->user(), $eventId);

        return response()->noContent();
    }
}
