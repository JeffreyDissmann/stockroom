<?php

declare(strict_types=1);

it('renders the login page in a real browser', function () {
    $page = visit('/login');

    $page->assertPresent('@login-submit')
        // Auth UI strings are translated via the auth_form group — a missing
        // group would leak raw keys like "auth_form.login.title".
        ->assertDontSee('auth_form.')
        ->assertNoJavaScriptErrors();
});
