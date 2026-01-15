<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Topic::create(['name' => 'Math']);
        Topic::create(['name' => 'Science']);
        Topic::create(['name' => 'History']);
        Topic::create(['name' => 'Literature']);
        Topic::create(['name' => 'Art']);
    }
}
