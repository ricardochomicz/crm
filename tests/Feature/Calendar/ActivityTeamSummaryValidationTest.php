<?php

namespace Tests\Feature\Calendar;

use App\Http\Requests\Calendar\CalendarEventRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ActivityTeamSummaryValidationTest extends TestCase
{
    private function errors(array $query): array
    {
        $request = CalendarEventRequest::create(
            '/api/v1/tenants/00000000-0000-4000-8000-000000000001/activities/team-summary',
            'GET',
            $query
        );

        return Validator::make($query, $request->rules())->errors()->toArray();
    }

    public function test_summary_accepts_default_period(): void
    {
        $this->assertSame([], $this->errors([]));
    }

    public function test_summary_accepts_inclusive_date_range(): void
    {
        $this->assertSame([], $this->errors([
            'from' => '2026-11-01',
            'to' => '2026-11-01',
        ]));
    }

    public function test_summary_rejects_inverted_period(): void
    {
        $errors = $this->errors([
            'from' => '2026-11-15',
            'to' => '2026-11-01',
        ]);

        $this->assertArrayHasKey('from', $errors);
    }

    public function test_summary_requires_both_dates(): void
    {
        $this->assertArrayHasKey('to', $this->errors(['from' => '2026-11-01']));
        $this->assertArrayHasKey('from', $this->errors(['to' => '2026-11-30']));
    }

    public function test_summary_rejects_invalid_date_format(): void
    {
        $this->assertArrayHasKey('from', $this->errors([
            'from' => '01/11/2026',
            'to' => '2026-11-30',
        ]));
    }
}
