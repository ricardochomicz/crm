<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimelineResource;
use App\Models\Timeline;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index()
    {
        return Timeline::orderBy('created_at', 'desc')->get();
    }


    public function store(Request $request)
    {
        $timeline = Timeline::create($request->all());
        $timeline->refresh();
        return new TimelineResource($timeline);
    }


    public function show(Timeline $timeline)
    {
        return new TimelineResource($timeline);
    }


    public function update(Request $request, Timeline $timeline)
    {
        $timeline->fill($request->all());
        $timeline->save();
        return new TimelineResource($timeline);
    }


    public function destroy(Timeline $timeline)
    {
        //
    }
}
