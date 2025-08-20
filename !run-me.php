<?php

/**
 * Spotify Image Fetcher for Seattle Goth Band Pages
 *
 * This script connects to the Spotify Web API to download real band images
 * for all 73 band pages in the acts directory.
 *
 * Run this script in an environment with internet access after adding
 * the return URL to your Spotify app settings at developer.spotify.com
 */

set_time_limit(120);

// Spotify API credentials
$client_id = '541cfb4cc3304dec99fe332cd30d04b1';
$client_secret = 'd831db3b102749009fb2de9c0d322760';

class SpotifyImageDownloader
{
    private $client_id;
    private $client_secret;
    private $access_token;
    private $acts_dir;
    private $images_dir;

    public function __construct($client_id, $client_secret)
    {
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
    private function getAccessToken()
    {
        echo "🔑 Requesting Spotify access token...\n";

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("cURL error: $curl_error");
        }

        if ($http_code !== 200) {
            throw new Exception("Failed to get access token. HTTP Code: $http_code, Response: $response");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("Invalid response format: " . $response);
        }

        $this->access_token = $data['access_token'];
        echo "✅ Spotify access token obtained successfully\n\n";
        return $this->access_token;
    }

    /**
     * Search for an artist on Spotify and return artist data
     */
    private function searchArtist($artist_name)
    {
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
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            echo "   ❌ cURL error searching for '$artist_name': $curl_error\n";
            return null;
        }

        if ($http_code !== 200) {
            echo "   ⚠️  Failed to search for '$artist_name'. HTTP Code: $http_code\n";
            return null;
        }

        $data = json_decode($response, true);

        if (empty($data['artists']['items'])) {
            echo "   ⚠️  No artist found on Spotify for '$artist_name'\n";
            return null;
        }

        return $data['artists']['items'][0];
    }

    /**
     * Download image from URL to local file
     */
    private function downloadImage($image_url, $filename)
    {
        echo "   📥 Downloading image from Spotify...\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            echo "   ❌ cURL error downloading image: $curl_error\n";
            return false;
        }

        if ($http_code !== 200 || !$image_data) {
            echo "   ❌ Failed to download image. HTTP Code: $http_code\n";
            return false;
        }

        $filepath = $this->images_dir . '/' . $filename;
        $bytes_written = file_put_contents($filepath, $image_data);

        if ($bytes_written === false) {
            echo "   ❌ Failed to save image to $filepath\n";
            return false;
        }

        echo "   ✅ Image saved: $filename (" . number_format($bytes_written) . " bytes)\n";
        return true;
    }

    /**
     * Extract band name from HTML file's <h1> tag
     */
    private function extractBandName($html_file)
    {
        $content = file_get_contents($html_file);

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }

    /**
     * Generate a clean filename from band name
     */
    private function generateImageFilename($band_name)
    {
        // Convert to lowercase and replace problematic characters
        $filename = strtolower($band_name);

        // Replace common problematic characters
        $replacements = [
            ' ' => '-',
            '&' => 'and',
            '(' => '',
            ')' => '',
            '.' => '',
            ',' => '',
            "'" => '',
            '"' => '',
            '!' => '',
            '?' => '',
            ':' => '',
            ';' => '',
            '/' => '-',
            '\\' => '-',
            '+' => 'plus',
            '=' => 'equals',
            '#' => 'hash',
            '@' => 'at',
            '%' => 'percent'
        ];

        foreach ($replacements as $search => $replace) {
            $filename = str_replace($search, $replace, $filename);
        }

        // Remove any remaining non-alphanumeric characters except hyphens
        $filename = preg_replace('/[^a-z0-9\-]/', '', $filename);

        // Remove multiple consecutive hyphens
        $filename = preg_replace('/-+/', '-', $filename);

        // Remove leading and trailing hyphens
        $filename = trim($filename, '-');

        return $filename . '.jpg';
    }

    /**
     * Process all band HTML files and download Spotify images
     */
    public function downloadAllSpotifyImages()
    {
        echo "🎵 Starting Spotify image download for Seattle Goth bands...\n";
        echo "📁 Images will be saved to: " . $this->images_dir . "\n\n";

        // Get all HTML files in acts directory
        $html_files = glob($this->acts_dir . '/*.html');
        $processed = 0;
        $success = 0;
        $total_bands = 0;

        foreach ($html_files as $file) {
            $filename = basename($file);

            // Skip index.html and TEMPLATE.html
            if ($filename === 'index.html' || $filename === 'TEMPLATE.html') {
                continue;
            }

            $total_bands++;
            echo "🎸 Processing band " . $total_bands . ": " . pathinfo($filename, PATHINFO_FILENAME) . "\n";

            // Extract band name from HTML
            $band_name = $this->extractBandName($file);
            if (!$band_name) {
                echo "   ❌ Could not extract band name from $filename\n\n";
                continue;
            }

            echo "   🏷️  Band name: '$band_name'\n";
            $processed++;

            // Search for artist on Spotify
            echo "   🔍 Searching Spotify for '$band_name'...\n";
            $artist = $this->searchArtist($band_name);

            if (!$artist) {
                echo "   ⚠️  Skipping - no Spotify results\n\n";
                continue;
            }

            echo "   ✅ Found on Spotify: " . $artist['name'] . "\n";
            echo "   👥 Followers: " . number_format($artist['followers']['total']) . "\n";

            // Get the largest image available
            if (empty($artist['images'])) {
                echo "   ⚠️  No images available for this artist\n\n";
                continue;
            }

            // Spotify returns images sorted by size (largest first)
            $largest_image = $artist['images'][0];
            $image_url = $largest_image['url'];

            echo "   🖼️  Found image: {$largest_image['width']}x{$largest_image['height']} pixels\n";

            // Generate filename and download
            $image_filename = $this->generateImageFilename($band_name);

            if ($this->downloadImage($image_url, $image_filename)) {
                $success++;
                echo "   🎉 Successfully downloaded image for '$band_name'\n";
            } else {
                echo "   ❌ Failed to download image for '$band_name'\n";
            }

            echo "\n";

            // Small delay to be respectful to Spotify's API
            usleep(100000); // 0.1 seconds
        }

        echo "📊 Download Summary:\n";
        echo "   Total band pages found: $total_bands\n";
        echo "   Bands processed: $processed\n";
        echo "   Images successfully downloaded: $success\n";
        echo "   Success rate: " . ($processed > 0 ? round(($success / $processed) * 100, 1) : 0) . "%\n\n";

        if ($success > 0) {
            echo "🎉 Spotify image download complete!\n";
            echo "   Your band pages now have real Spotify artist images.\n";
            echo "   Check the acts/images/ directory to see the downloaded images.\n";
        } else {
            echo "⚠️  No images were downloaded. Please check:\n";
            echo "   - Internet connection\n";
            echo "   - Spotify API credentials\n";
            echo "   - Return URL configured in Spotify app settings\n";
        }
    }
}

// Main execution
echo "🚀 Spotify Image Fetcher for Seattle Goth Bands\n";
echo "===============================================\n\n";

try {
    $downloader = new SpotifyImageDownloader($client_id, $client_secret);
    $downloader->downloadAllSpotifyImages();
} catch (Exception $e) {
    echo "💥 Error: " . $e->getMessage() . "\n";
    echo "   Please check your internet connection and Spotify API credentials.\n";
    exit(1);
}

echo "\n✨ Script completed!\n";
