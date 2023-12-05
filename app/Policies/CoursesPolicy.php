<?php

namespace App\Policies;

use App\Models\Courses;
use App\Models\User;

class CoursesPolicy {
	/**
	 * Determine whether the user can view any models.
	 */
	public function viewAny(User $user): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}

	/**
	 * Determine whether the user can view the model.
	 */
	public function view(User $user, Courses $courses): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}

	/**
	 * Determine whether the user can create models.
	 */
	public function create(User $user): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}

	/**
	 * Determine whether the user can update the model.
	 */
	public function update(User $user, Courses $courses): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}

	/**
	 * Determine whether the user can delete the model.
	 */
	public function delete(User $user, Courses $courses): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}

	/**
	 * Determine whether the user can restore the model.
	 */
	public function restore(User $user, Courses $courses): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}

	/**
	 * Determine whether the user can permanently delete the model.
	 */
	public function forceDelete(User $user, Courses $courses): bool {
		if ($user->isAdmin === 1) {
			return true;
		}
		return $user->id === $post->user_id;
	}
}
