<?php

use App\Models\User;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Login;

uses(\Tests\DuskTestCase::class);

/** @test */
it('can login onboarded users', function () {
    $user = User::factory()->create();
    $this->createUserWorkspace($user);

    $this->browse(function ($browser) use ($user) {
        $browser->visit(new Login())
            ->submit($user->email, 'Abcd@1234')
            ->assertPageIs(Home::class);
    });
});

it('cannot login with invalid credentials', function () {
    $this->browse(function ($browser) {
        $browser->visit(new Login())
            ->submit('test@test.app', 'password')
            ->pause(100)
            ->assertSee('These credentials do not match our records.');
    });
});

it('can log out the user', function () {
    $user = User::factory()->create();

    $this->browse(function ($browser) use ($user) {
        $browser->visit(new Login())
            ->submit($user->email, 'Abcd@1234')
            ->on(new Home())
            ->clickLogout()
            ->assertPageIs(Login::class);
    });
});
