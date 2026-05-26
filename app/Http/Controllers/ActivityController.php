<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ActivityPresenter;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityPresenter $presenter) {}

    public function __invoke(): Response
    {
        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->latest()
            ->latest('id')
            ->paginate(30)
            ->through(fn (Activity $activity): array => $this->presenter->present($activity));

        return Inertia::render('Activity', [
            'activities' => $activities,
        ]);
    }
}
