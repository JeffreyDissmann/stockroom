<?php

declare(strict_types=1);

it('renders the login page in a real browser', function () {
    $page = visit('/login');

    $page->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});
