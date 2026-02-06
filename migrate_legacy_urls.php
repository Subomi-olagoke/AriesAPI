<?php
/**
 * One-time migration script to move legacy url_items to library_content table
 * Run this script once to clean up legacy data
 */

// Database connection
$dbUrl = 'postgresql://postgres:feNIDxcvQeTilZuHZHKnWOSNYCOwLfds@gondola.proxy.rlwy.net:15799/railway';

try {
    // Parse database URL
    $parts = parse_url($dbUrl);
    $host = $parts['host'];
    $port = $parts['port'];
    $database = ltrim($parts['path'], '/');
    $username = $parts['user'];
    $password = $parts['pass'];
    
    // Connect to PostgreSQL
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$database",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to database\n\n";
    
    // Get all libraries with url_items
    $stmt = $pdo->query("
        SELECT id, name, url_items 
        FROM open_libraries 
        WHERE url_items IS NOT NULL 
        AND jsonb_array_length(url_items) > 0
        ORDER BY id
    ");
    
    $libraries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($libraries) . " libraries with legacy url_items\n\n";
    
    $totalMigrated = 0;
    $totalSkipped = 0;
    $errors = 0;
    
    foreach ($libraries as $library) {
        $libraryId = $library['id'];
        $libraryName = $library['name'];
        $urlItems = json_decode($library['url_items'], true);
        
        if (!is_array($urlItems)) {
            echo "⚠ Library $libraryId ($libraryName): Invalid url_items format\n";
            continue;
        }
        
        echo "Processing Library $libraryId: $libraryName (" . count($urlItems) . " URLs)\n";
        
        foreach ($urlItems as $urlItem) {
            try {
                $url = $urlItem['url'] ?? null;
                $title = $urlItem['title'] ?? 'No title';
                $summary = $urlItem['summary'] ?? $urlItem['description'] ?? '';
                $notes = $urlItem['notes'] ?? '';
                $relevanceScore = $urlItem['relevance_score'] ?? 0.5;
                
                if (empty($url)) {
                    echo "  ⚠ Skipped: No URL\n";
                    $totalSkipped++;
                    continue;
                }
                
                // Check if URL already exists in library_urls
                $stmt = $pdo->prepare("SELECT id FROM library_urls WHERE url = ?");
                $stmt->execute([$url]);
                $existingUrl = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $urlId = null;
                
                if (!$existingUrl) {
                    // Create new URL entry
                    $stmt = $pdo->prepare("
                        INSERT INTO library_urls (url, title, summary, notes, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                        RETURNING id
                    ");
                    $stmt->execute([$url, $title, $summary, $notes]);
                    $urlId = $stmt->fetchColumn();
                    echo "  ✓ Created new URL: $title\n";
                } else {
                    $urlId = $existingUrl['id'];
                }
                
                // Check if this URL is already in library_content for this library
                $stmt = $pdo->prepare("
                    SELECT id FROM library_content 
                    WHERE library_id = ? 
                    AND content_id = ? 
                    AND content_type = 'App\\\\Models\\\\LibraryUrl'
                ");
                $stmt->execute([$libraryId, $urlId]);
                
                if ($stmt->fetch()) {
                    echo "  → Already in library_content, skipping\n";
                    $totalSkipped++;
                    continue;
                }
                
                // Insert into library_content
                $stmt = $pdo->prepare("
                    INSERT INTO library_content (library_id, content_id, content_type, relevance_score, notes, created_at, updated_at)
                    VALUES (?, ?, 'App\\\\Models\\\\LibraryUrl', ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$libraryId, $urlId, $relevanceScore, $notes]);
                
                echo "  ✓ Migrated: $title\n";
                $totalMigrated++;
                
            } catch (Exception $e) {
                echo "  ✗ Error migrating URL: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        echo "\n";
    }
    
    echo "\n=== Migration Summary ===\n";
    echo "Total migrated: $totalMigrated\n";
    echo "Total skipped (already exist): $totalSkipped\n";
    echo "Errors: $errors\n";
    echo "\n✓ Migration complete!\n";
    
    echo "\n⚠ NEXT STEP: Review the results, then update OpenLibraryController.php to remove legacy fallback code\n";
    
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
