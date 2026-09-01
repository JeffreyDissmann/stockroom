<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomField;

/**
 * Identical payload to store — a custom field has the same shape whether it is
 * being created or renamed. Extends rather than restates it so the two cannot
 * drift, the way UpdateMaintenanceTaskRequest already extends its store request.
 */
class UpdateCustomFieldRequest extends StoreCustomFieldRequest {}
