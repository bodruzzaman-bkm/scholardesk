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
            
            // 1. Format authors array into a comma-separated string
            $authors = null;
            if (isset($data['author'])) {
                $authorsList = array_map(function($author) {
                    return trim(($author['given'] ?? '') . ' ' . ($author['family'] ?? ''));
                }, $data['author']);
                $authors = implode(', ', $authorsList);
            }

            // 2. Clean up the abstract text (remove tags and leading 'Abstract' word)
            $abstractText = null;
            if (isset($data['abstract'])) {
                $abstractText = strip_tags($data['abstract']);
                $abstractText = preg_replace('/^Abstract\s*/i', '', $abstractText);
            }

            // 3. Return the metadata as an array to the controller
            return [
                'title' => isset($data['title'][0]) ? strip_tags($data['title'][0]) : null,
                'abstract' => $abstractText,
                'venue' => $data['container-title'][0] ?? null,
                'year' => $data['published-print']['date-parts'][0][0] ?? null,
                'authors' => $authors, // Now $authors is perfectly defined!
            ];
        }

        // Return null if the API call fails
        return null; 
    }
}