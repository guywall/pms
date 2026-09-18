<?php

it('redirects guests from the root to the staff panel', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
