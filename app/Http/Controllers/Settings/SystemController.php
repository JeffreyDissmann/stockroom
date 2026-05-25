<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SystemController extends Controller
{
    /**
     * The System page groups app-level operations (backup/restore today, more
     * subfeatures later). Each subfeature owns its own controller + endpoints.
     */
    public function index(): Response
    {
        return Inertia::render('settings/System');
    }
}
