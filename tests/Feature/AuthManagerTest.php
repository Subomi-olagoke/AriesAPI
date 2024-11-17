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

    /**
 * @test
    */
    public function logoutTest() {
        $rawPassword = 'ValidPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($rawPassword)
        ]);

        $loginData = [
            'username' => $user->username,
            'password' => $rawPassword
        ];

        $loginResponse = $this->postJson(route('login'), $loginData);
        $loginResponse->assertStatus(200)
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

         $loginResponse->assertCookie('access_token');

         $token = $loginResponse->json('access_token');

         //dd($loginResponse->json('access_token'));


         // Send logout request with the Authorization header
         $logoutResponse = $this->withHeaders([
             'Authorization' => 'Bearer ' . $token,
         ])->postJson(route('logout'));

         $logoutResponse->assertStatus(200)
             ->assertJson([
                 'message' => 'Logged out successfully',
             ]);
             //expect($this->user->fresh()->tokens)->toBeEmpty();

             $this->refreshApplication();
             $this->refreshDatabase();



         // Verify that the token is no longer valid
         $this->withHeaders([
             'Authorization' => 'Bearer ' . $token,
         ])->getJson(route('profile.view', ['user' => $user->username]))
             ->assertStatus(401); // Token should be invalid after logout

    }

      /**
 * @test
    */
    public function resetPasswordRequestTest() {
    $user = User::factory()->create([
            'email' => 'fring@gmail.com'
        ]);

       $this->assertDatabaseHas('users', [
            'email' => $user['email'],
        ]);
        $data = [
           'email' => $user->email
        ];

        $resetPassReq = $this->postJson(route('resetPassReq'), $data);

        $resetPassReq->assertOk();

        $bad = [
            'email' => 'younger@gmail.com'
        ];

        $badReq = $this->postJson(route('resetPassReq'), $bad);
        $badReq->assertStatus(404);

    }

    public function resetPasswordTest() {

    }

}
