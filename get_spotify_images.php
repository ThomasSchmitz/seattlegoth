<?php
/**
 * Script to fetch Spotify images for band pages and update HTML files
 */

// Spotify API credentials
$client_id = '541cfb4cc3304dec99fe332cd30d04b1';
$client_secret = 'd831db3b102749009fb2de9c0d322760';

class SpotifyImageFetcher {
    private $client_id;
    private $client_secret;
    private $access_token;
    private $acts_dir;
    private $images_dir;
    
    public function __construct($client_id, $client_secret) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->acts_dir = __DIR__ . '/acts';
        $this->images_dir = $this->acts_dir . '/images';
        
        // Create images directory if it doesn't exist
        if (!is_dir($this->images_dir)) {
            mkdir($this->images_dir, 0755, true);
        }
    }
    
    /**
     * Get Spotify access token using Client Credentials flow
     */
    private function getAccessToken() {
        $url = 'https://accounts.spotify.com/api/token';
        
        $data = [
            'grant_type' => 'client_credentials'
        ];
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Failed to get access token. HTTP Code: $http_code, Response: $response");
        }
        
        $data = json_decode($response, true);
        $this->access_token = $data['access_token'];
        
        echo "✓ Spotify access token obtained\n";
        return $this->access_token;
    }
    
    /**
     * Search for an artist on Spotify
     */
    private function searchArtist($artist_name) {
        if (!$this->access_token) {
            $this->getAccessToken();
        }
        
        $url = 'https://api.spotify.com/v1/search';
        $params = [
            'q' => $artist_name,
            'type' => 'artist',
            'limit' => 1
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->access_token
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            echo "⚠ Failed to search for artist '$artist_name'. HTTP Code: $http_code\n";
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (empty($data['artists']['items'])) {
            echo "⚠ No artist found for '$artist_name'\n";
            return null;
        }
        
        return $data['artists']['items'][0];
    }
    
    /**
     * Download image from URL
     */
    private function downloadImage($image_url, $filename) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$image_data) {
            return false;
        }
        
        $filepath = $this->images_dir . '/' . $filename;
        return file_put_contents($filepath, $image_data) !== false;
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
     * Update HTML file with image
     */
    private function updateHtmlFile($html_file, $image_filename, $band_name) {
        $content = file_get_contents($html_file);
        
        // Replace the placeholder div with an img tag
        $placeholder = '<div class="placeholder-image">' . "\n" . 
                      '                [Band Image - To be added to images/ directory]' . "\n" . 
                      '            </div>';
        
        $image_tag = '<img src="images/' . $image_filename . '" alt="' . htmlspecialchars($band_name) . '" class="band-image">';
        
        $updated_content = str_replace($placeholder, $image_tag, $content);
        
        if ($updated_content !== $content) {
            file_put_contents($html_file, $updated_content);
            return true;
        }
        
        return false;
    }
    
    /**
     * Process all band pages
     */
    public function processAllBands() {
        $html_files = glob($this->acts_dir . '/*.html');
        $processed = 0;
        $total = 0;
        
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
            
            $total++;
            echo "\n--- Processing: $filename ---\n";
            
            // Extract band name
            $band_name = $this->extractBandName($file);
            if (!$band_name) {
                echo "⚠ Could not extract band name from $filename\n";
                continue;
            }
            
            echo "Band name: $band_name\n";
            
            // Search for artist on Spotify
            $artist = $this->searchArtist($band_name);
            if (!$artist) {
                continue;
            }
            
            // Get the largest image
            if (empty($artist['images'])) {
                echo "⚠ No images found for '$band_name'\n";
                continue;
            }
            
            // Spotify returns images sorted by size (largest first)
            $largest_image = $artist['images'][0];
            $image_url = $largest_image['url'];
            
            echo "Found image: {$largest_image['width']}x{$largest_image['height']} px\n";
            
            // Generate filename
            $image_filename = strtolower(str_replace([' ', '&', '(', ')', '.'], ['-', 'and', '', '', ''], $band_name)) . '.jpg';
            $image_filename = preg_replace('/[^a-z0-9\-\.]/', '', $image_filename);
            
            // Download image
            if ($this->downloadImage($image_url, $image_filename)) {
                echo "✓ Downloaded: $image_filename\n";
                
                // Update HTML file
                if ($this->updateHtmlFile($file, $image_filename, $band_name)) {
                    echo "✓ Updated HTML file\n";
                    $processed++;
                } else {
                    echo "⚠ Failed to update HTML file\n";
                }
            } else {
                echo "⚠ Failed to download image\n";
            }
        }
        
        echo "\n=== Summary ===\n";
        echo "Total bands processed: $processed / $total\n";
    }
}

// Run the script
try {
    $fetcher = new SpotifyImageFetcher($client_id, $client_secret);
    $fetcher->processAllBands();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>