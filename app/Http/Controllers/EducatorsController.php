<?php

namespace App\Http\Controllers;

use App\Models\Courses;
use App\Models\Educators;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EducatorsController extends Controller {

	public function storeAvatar(Request $request) {
		$request->file('avatar')->store();
	}

	public function register(Request $request, Response $response) {
		$incomingFields = $request->validate([
			'firstName' => ['required'],
			'LastName' => ['required'],
			'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
			'password' => ['required', 'min:8', 'max:20', 'confirmed'],
			'field' => ['required', Rule::in(['Business', 'Advertising', 'Copy-writing', 'Story Writing', 'Mathematics', 'Computer Science', 'Programming', 'Architecture', 'Engineering', 'others'])],

		]);

		$incomingFields['password'] = bcrypt($incomingFields['password']);

		$Educators = Educators::create($incomingFields);
		auth()->login($Educators);
		return response()->json($response);
		// return redirect('/');

	}

	function login(Request $request, Response $response) {
		$incomingFields = $request->validate([
			'email' => 'required',
			'password' => 'required',
		]);
		if (auth()->attempt(['email' => $incomingFields['email'], 'password' => $incomingFields['password']])) {
			return response()->json($response);
		}
		return response()->json('invalid login');

	}

	/*public function EducatorProfile(Educators $educator) {
		$user = Auth::user();
		$profile = Educators::where('user_id', $user->id)->first();
		return response()->json(['profile' => $profile]);
	}*/

	/*public function update(Request $request) {
		$user = Auth::user();
		$profile = Educators::where('user_id', $user->id)->first();

		if (!$profile) {
			$profile = new Educators(['user_id' => $user->id]);
		}

		$profile->fill($request->all());
		$profile->save();

		return response()->json(['profile' => $profile]);
	}*/

	//post courses
	public function storeCourse(Request $request) {

		$data = new Courses();
		$file = $request->file;

		$filename = time() . '.' . $file->getClientOriginalExtension();

		$request->file->move('assets', $filename);

		$data->file = $filename;

		$data->name = $request->name;

		$data->description = $request->description;

		$data->save();
		return redirect()->back();
	}

	public function show() {
		$data = Courses::all();
		return response()->json(['data' => $data]);
	}

	public function download(Request $request, $file) {

		return response()->download(public_path('assets/' . $file));

	}

	public function view($id) {
		$data = Courses::find($id);

		return response()->json(['data' => $data]);
	}

}
