<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CrossRefService
{
    public function fetchMetadata($doi)
    {
        // Clean the DOI string by removing the URL parts
        $cleanDoi = str_replace(['https://doi.org/', 'http://doi.org/'], '', $doi);
        
        // Call the CrossRef API
        $response = Http::get("https://api.crossref.org/works/{$cleanDoi}");
        
        if ($response->successful()) {
            $data = $response->json()['message'];
            
            // Format authors array into a comma-separated string
            $authors = null;
            if (isset($data['author'])) {
                $authorsList = array_map(function($author) {
                    return trim(($author['given'] ?? '') . ' ' . ($author['family'] ?? ''));
                }, $data['author']);
                $authors = implode(', ', $authorsList);
            }

            // Return the metadata as an array to the controller
            return [
                'title' => $data['title'][0] ?? null,
                'abstract' => $data['abstract'] ?? null,
                'venue' => $data['container-title'][0] ?? null,
                'year' => $data['published-print']['date-parts'][0][0] ?? null,
                'authors' => $authors,
            ];
        }

        // Return null if the API call fails
        return null; 
    }
}