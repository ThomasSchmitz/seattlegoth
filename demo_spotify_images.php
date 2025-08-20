<?php
/**
 * Demo script to show how Spotify image integration would work
 * This creates sample images and updates HTML files to demonstrate the process
 */

class SpotifyImageDemo {
    private $acts_dir;
    private $images_dir;
    
    public function __construct() {
        $this->acts_dir = __DIR__ . '/acts';
        $this->images_dir = $this->acts_dir . '/images';
        
        // Create images directory if it doesn't exist
        if (!is_dir($this->images_dir)) {
            mkdir($this->images_dir, 0755, true);
        }
    }
    
    /**
     * Extract band name from HTML file
     */
    private function extractBandName($html_file) {
        $content = file_get_contents($html_file);
        
        // Look for <h1> tag which should contain the band name
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        return null;
    }
    
    /**
     * Create a sample image for the band
     */
    private function createSampleImage($band_name, $filename) {
        $width = 400;
        $height = 400;
        
        // Create image
        $image = imagecreate($width, $height);
        
        // Colors
        $bg_color = imagecolorallocate($image, 30, 30, 30); // Dark background
        $text_color = imagecolorallocate($image, 255, 255, 255); // White text
        $border_color = imagecolorallocate($image, 204, 0, 0); // Red border
        
        // Fill background
        imagefill($image, 0, 0, $bg_color);
        
        // Draw border
        imagerectangle($image, 0, 0, $width-1, $height-1, $border_color);
        imagerectangle($image, 1, 1, $width-2, $height-2, $border_color);
        
        // Add text
        $font_size = 5;
        $text1 = "SAMPLE IMAGE";
        $text2 = $band_name;
        $text3 = "FROM SPOTIFY";
        
        // Calculate text positions
        $text1_x = ($width - strlen($text1) * imagefontwidth($font_size)) / 2;
        $text2_x = ($width - strlen($text2) * imagefontwidth($font_size)) / 2;
        $text3_x = ($width - strlen($text3) * imagefontwidth($font_size)) / 2;
        
        // Draw text
        imagestring($image, $font_size, $text1_x, 150, $text1, $text_color);
        imagestring($image, $font_size, $text2_x, 180, $text2, $text_color);
        imagestring($image, $font_size, $text3_x, 210, $text3, $text_color);
        
        // Save image
        $filepath = $this->images_dir . '/' . $filename;
        $success = imagejpeg($image, $filepath, 90);
        
        // Clean up
        imagedestroy($image);
        
        return $success;
    }
    
    /**
     * Update HTML file with image
     */
    private function updateHtmlFile($html_file, $image_filename, $band_name) {
        $content = file_get_contents($html_file);
        
        // Look for the placeholder div pattern
        $patterns = [
            // Pattern 1: Standard placeholder
            '/(<div class="placeholder-image">\s*)\[Band Image - To be added to images\/ directory\](\s*<\/div>)/s',
            // Pattern 2: With extra whitespace
            '/(<div class="placeholder-image"[^>]*>\s*)\[Band Image - To be added to images\/ directory\](\s*<\/div>)/s'
        ];
        
        $replacement = '$1<img src="images/' . $image_filename . '" alt="' . htmlspecialchars($band_name) . '" class="band-image" style="max-width: 100%; height: auto; border-radius: 8px;">$2';
        
        $updated_content = $content;
        foreach ($patterns as $pattern) {
            $updated_content = preg_replace($pattern, $replacement, $updated_content);
        }
        
        // Also try direct string replacement as fallback
        if ($updated_content === $content) {
            $placeholder_text = '[Band Image - To be added to images/ directory]';
            $image_html = '<img src="images/' . $image_filename . '" alt="' . htmlspecialchars($band_name) . '" class="band-image" style="max-width: 100%; height: auto; border-radius: 8px;">';
            $updated_content = str_replace($placeholder_text, $image_html, $content);
        }
        
        if ($updated_content !== $content) {
            file_put_contents($html_file, $updated_content);
            return true;
        }
        
        return false;
    }
    
    /**
     * Process specific bands for demo
     */
    public function processDemoBands($limit = 5) {
        $html_files = glob($this->acts_dir . '/*.html');
        $processed = 0;
        $total = 0;
        
        foreach ($html_files as $file) {
            if ($processed >= $limit) break;
            
            $filename = basename($file);
            
            // Skip index.html and TEMPLATE.html
            if ($filename === 'index.html' || $filename === 'TEMPLATE.html') {
                continue;
            }
            
            // Check if file has placeholder text
            $content = file_get_contents($file);
            if (strpos($content, '[Band Image - To be added to images/ directory]') === false) {
                continue;
            }
            
            $total++;
            echo "\n--- Processing: $filename ---\n";
            
            // Extract band name
            $band_name = $this->extractBandName($file);
            if (!$band_name) {
                echo "⚠ Could not extract band name from $filename\n";
                continue;
            }
            
            echo "Band name: $band_name\n";
            
            // Generate filename
            $image_filename = strtolower(str_replace([' ', '&', '(', ')', '.', '/'], ['-', 'and', '', '', '', '-'], $band_name)) . '.jpg';
            $image_filename = preg_replace('/[^a-z0-9\-\.]/', '', $image_filename);
            $image_filename = preg_replace('/-+/', '-', $image_filename); // Remove multiple dashes
            
            echo "Image filename: $image_filename\n";
            
            // Create sample image
            if ($this->createSampleImage($band_name, $image_filename)) {
                echo "✓ Created sample image: $image_filename\n";
                
                // Update HTML file
                if ($this->updateHtmlFile($file, $image_filename, $band_name)) {
                    echo "✓ Updated HTML file\n";
                    $processed++;
                } else {
                    echo "⚠ Failed to update HTML file\n";
                }
            } else {
                echo "⚠ Failed to create sample image\n";
            }
        }
        
        echo "\n=== Demo Summary ===\n";
        echo "Demo bands processed: $processed / $total\n";
        echo "Sample images created in: {$this->images_dir}\n";
        echo "\nTo use real Spotify images:\n";
        echo "1. Run get_spotify_images.php with internet access\n";
        echo "2. The script will fetch actual band images from Spotify\n";
        echo "3. Replace sample images with real ones\n";
    }
    
    /**
     * Show all bands that need images
     */
    public function listBandsNeedingImages() {
        $html_files = glob($this->acts_dir . '/*.html');
        $bands = [];
        
        foreach ($html_files as $file) {
            $filename = basename($file);
            
            // Skip index.html and TEMPLATE.html
            if ($filename === 'index.html' || $filename === 'TEMPLATE.html') {
                continue;
            }
            
            // Check if file has placeholder text
            $content = file_get_contents($file);
            if (strpos($content, '[Band Image - To be added to images/ directory]') === false) {
                continue;
            }
            
            $band_name = $this->extractBandName($file);
            if ($band_name) {
                $bands[] = [
                    'file' => $filename,
                    'name' => $band_name
                ];
            }
        }
        
        echo "Bands needing images (" . count($bands) . " total):\n";
        echo "=====================================\n";
        foreach ($bands as $band) {
            echo "• {$band['name']} ({$band['file']})\n";
        }
        
        return $bands;
    }
}

// Check command line arguments
$action = isset($argv[1]) ? $argv[1] : 'demo';

$demo = new SpotifyImageDemo();

switch ($action) {
    case 'list':
        $demo->listBandsNeedingImages();
        break;
    case 'demo':
    default:
        $demo->processDemoBands(5);
        break;
}

?>