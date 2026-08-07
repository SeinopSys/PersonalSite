<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SleepExceptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'testuser',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
            'lang'     => 'en',
            'role'     => 'user',
        ]);
    }

    public function test_user_can_create_a_sleep_exception(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/dashboard/sleep-exceptions', [
            'start_date' => '2030-07-01',
            'end_date'   => '2030-07-10',
            'label'      => 'Travel',
        ]);

        $response->assertRedirect('/availability#sleep-exceptions');
        $this->assertDatabaseHas('sleep_exceptions', [
            'user_id'    => $user->id,
            'start_date' => '2030-07-01',
            'end_date'   => '2030-07-10',
            'label'      => 'Travel',
        ]);
    }

    public function test_label_is_optional(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/dashboard/sleep-exceptions', [
            'start_date' => '2030-07-01',
            'end_date'   => '2030-07-10',
        ]);

        $response->assertRedirect('/availability#sleep-exceptions');
        $this->assertDatabaseHas('sleep_exceptions', [
            'user_id'    => $user->id,
            'start_date' => '2030-07-01',
            'end_date'   => '2030-07-10',
            'label'      => null,
        ]);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/dashboard/sleep-exceptions', [
            'start_date' => '2030-07-10',
            'end_date'   => '2030-07-01',
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseMissing('sleep_exceptions', ['user_id' => $user->id]);
    }

    public function test_start_and_end_date_are_required(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/dashboard/sleep-exceptions', []);

        $response->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_user_can_delete_own_sleep_exception(): void
    {
        $user = $this->makeUser();
        $exception = $user->sleepExceptions()->create(['start_date' => '2030-07-01', 'end_date' => '2030-07-10']);

        $response = $this->actingAs($user)->delete("/dashboard/sleep-exceptions/{$exception->id}");

        $response->assertRedirect('/availability#sleep-exceptions');
        $this->assertDatabaseMissing('sleep_exceptions', ['id' => $exception->id]);
    }

    public function test_user_cannot_delete_another_users_sleep_exception(): void
    {
        $owner = $this->makeUser();
        $exception = $owner->sleepExceptions()->create(['start_date' => '2030-07-01', 'end_date' => '2030-07-10']);

        $otherUser = User::create([
            'name'     => 'otheruser',
            'email'    => 'other@example.com',
            'password' => bcrypt('password'),
            'lang'     => 'en',
            'role'     => 'user',
        ]);

        $response = $this->actingAs($otherUser)->delete("/dashboard/sleep-exceptions/{$exception->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('sleep_exceptions', ['id' => $exception->id]);
    }

    public function test_deleting_user_cascades_their_sleep_exceptions(): void
    {
        $user = $this->makeUser();
        $exception = $user->sleepExceptions()->create(['start_date' => '2030-07-01', 'end_date' => '2030-07-10']);

        $user->delete();

        $this->assertDatabaseMissing('sleep_exceptions', ['id' => $exception->id]);
    }

    public function test_availability_page_lists_sleep_exceptions(): void
    {
        $user = $this->makeUser();
        $user->sleepExceptions()->create(['start_date' => '2030-07-01', 'end_date' => '2030-07-10', 'label' => 'Travel']);

        $response = $this->actingAs($user)->get('/availability');

        $response->assertOk();
        $response->assertSee('Travel');
        $response->assertSee('2030-07-01');
        $response->assertSee('2030-07-10');
    }
}
