<?php
/**
 * Setup Votes Table - Run this script to create the votes table in your database
 * 
 * Usage: php setup_votes_table.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking if votes table exists...\n";
    
    // Check if table already exists
    if (Schema::hasTable('votes')) {
        echo "✅ Votes table already exists!\n";
        
        // Check columns
        $columns = Schema::getColumnListing('votes');
        echo "   Columns: " . implode(', ', $columns) . "\n";
        
        // Count existing votes
        $count = DB::table('votes')->count();
        echo "   Current votes count: {$count}\n";
        
    } else {
        echo "📝 Creating votes table...\n";
        
        // Create the votes table
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // UUID string
            $table->morphs('voteable'); // voteable_id and voteable_type
            $table->enum('vote_type', ['up', 'down']); // upvote or downvote
            $table->timestamps();
            
            // Ensure one vote per user per content item
            $table->unique(['user_id', 'voteable_id', 'voteable_type']);
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['voteable_type', 'voteable_id']);
            $table->index('user_id');
        });
        
        echo "✅ Votes table created successfully!\n";
    }
    
    echo "\n🎉 Setup complete! Reddit-style voting is ready to use.\n";
    
    // Show sample vote record structure
    echo "\n📋 Vote Record Structure:\n";
    echo "   {\n";
    echo "     id: bigint,\n";
    echo "     user_id: string (UUID),\n";
    echo "     voteable_id: bigint,\n";
    echo "     voteable_type: string (e.g., 'App\\Models\\LibraryUrl'),\n";
    echo "     vote_type: 'up' | 'down',\n";
    echo "     created_at: timestamp,\n";
    echo "     updated_at: timestamp\n";
    echo "   }\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Troubleshooting:\n";
    echo "   1. Make sure you're running this on the server (not locally)\n";
    echo "   2. Check database connection in .env file\n";
    echo "   3. Alternatively, run the SQL script directly:\n";
    echo "      psql \$DATABASE_URL < create_votes_table.sql\n";
    exit(1);
}

