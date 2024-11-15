<?php

namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;


class AuthManagerTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    /**
 * @test
    */

    public function registrationTest() {
        $data = [
           'first_name' => 'John',
           'last_name' => 'Okotomeowmeow',
           'username' => $this->faker->unique()->userName(),
           'email' => $this->faker->unique()->safeEmail(),
           'password' => 'ValidPassword123!',
           'password_confirmation' => 'ValidPassword123!',
       ];

       $response = $this->postJson(route('register'), $data);

       $this->assertDatabaseHas('users', [
           'email' => $data['email'],
           'username' => $data['username'],
       ]);

         // Assert the response has the correct success message
         $response->assertStatus(200)
         ->assertJson([
             'message' => 'Registration successful',
         ]);
    }

    public function loginTest() {

    }



}
