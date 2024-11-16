<?php

namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;


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

      /**
 * @test
    */
    public function loginTest() {
        $rawPassword = 'ValidPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($rawPassword)
        ]);

        $data = [
            'username' => $user->username,
            'password' => $rawPassword
        ];

        $response = $this->postJson(route('login'), $data);


        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => [
                         'id',
                         'username',
                         'email',
                         'first_name',
                         'last_name',
                     ],
                     'access_token',
                     'token_type',
                 ]);

         $response->assertCookie('access_token');
    }



}
