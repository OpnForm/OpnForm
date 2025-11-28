<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\Onboarding;
use Tests\Browser\Pages\Register;
use Tests\DuskTestCase;

class RegisterTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setup();

        static::closeAll();
    }

    /**
     * Pick Random option from custom select
     *
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function selectHearAboutUsReason(Browser $browser)
    {
        $browser->waitFor('@hear_about_us')
            ->click('@hear_about_us')
            ->waitFor('@hear_about_us_dropdown');
        $options = $browser->elements('@hear_about_us_option');
        shuffle($options);
        $options[0]->click();
    }

    /** @test */
    public function register_with_valid_data()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Register());
            $this->selectHearAboutUsReason($browser);
            $browser->submit([
                'name' => 'Test User',
                'email' => 'testuser@test.test',
                'password' => 'Abcd@1234',
                'password_confirmation' => 'Abcd@1234',
            ])
                ->assertPageIs(Onboarding::class);
        });
    }

    /** @test */
    public function can_not_register_with_the_same_twice()
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new Register());
            $this->selectHearAboutUsReason($browser);
            $browser->submit([
                'name' => 'Test User',
                'email' => $user->email,
                'password' => 'Abcd@1234',
                'password_confirmation' => 'Abcd@1234',
            ])
                ->pause(3000)
                ->assertSee('The email has already been taken.');
        });
    }
}
