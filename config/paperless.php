<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Paperless-ngx Integration
    |--------------------------------------------------------------------------
    |
    | Stockroom's Paperless integration is webhook-driven (#7): when a doc
    | gets tagged in Paperless, a workflow POSTs to our webhook endpoint,
    | which fans out to a queue job that asks the AI to extract inventory
    | items from the OCR text and links them back via a Paperless custom
    | field.
    |
    | Leaving `url` empty disables the integration end-to-end — the webhook
    | route 404's, the AI agent isn't registered, and the "From document"
    | link on item Show won't render. The setup is single-instance: one
    | Paperless per Stockroom, not per household.
    |
    */

    'url' => env('PAPERLESS_URL'),
    'token' => env('PAPERLESS_TOKEN'),

    /*
    | Static shared secret compared timing-safely against the
    | `X-Stockroom-Secret` header on every webhook request. Paperless's
    | workflow webhook action can only send STATIC headers (no per-body
    | HMAC), so we use a fixed token instead of a signature. Without it
    | the route 503s — see VerifyPaperlessSignature.
    */
    'webhook_secret' => env('PAPERLESS_WEBHOOK_SECRET'),

    /*
    | Tag names that drive the workflow. The trigger tag is what the user
    | adds in Paperless to start the import; once a doc is processed it's
    | swapped to `linked_tag` so the workflow doesn't re-fire on the same
    | document. Re-tagging with `trigger_tag` is the explicit re-run signal.
    */
    'trigger_tag' => env('PAPERLESS_TRIGGER_TAG', 'Add to Stockroom'),
    'linked_tag' => env('PAPERLESS_LINKED_TAG', 'Stockroom'),

    /*
    | Name of the Paperless *URL custom field* that stores a backlink to
    | Stockroom for a processed document. Written once on intake, points at
    | Stockroom's search page filtered to the items linked to this doc —
    | click it in Paperless to land on the matching items list.
    */
    'link_custom_field' => env('PAPERLESS_LINK_CUSTOM_FIELD', 'Stockroom URL'),
];
