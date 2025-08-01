<?php

namespace App\Command;

use App\Service\NotionService;
use App\Service\SpotifyService;
use App\Service\SpotifyWebAPIWrapper;
use SpotifyWebAPI\SpotifyWebAPI;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'spotify:update-playlist',
    description: 'Updates the Spotify playlist for the current month using artists from Notion'
)]
class UpdatePlaylistCommand extends Command
{
    private SpotifyService $spotifyService;
    private NotionService $notionService;
    private static array $blacklistedWords = ['instrumental', 'acoustic', 'live', 'karaoke', 'remix', 'cover', 'remaster', 'edition', 'version', 'session', 'demo', 'mix', 'track', 'original', 'edit', 'extended'];

    public function __construct(SpotifyService $spotifyService, NotionService $notionService)
    {
        parent::__construct();
        $this->spotifyService = $spotifyService;
        $this->notionService = $notionService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Updates the Spotify playlist for the current month using artists from Notion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Step 1: Get artists from Notion
            $output->writeln('Fetching artists from Notion...');
            $artistUris = $this->notionService->getArtistsFromNotion();

            if (empty($artistUris)) {
                $output->writeln('<error>No artists found in Notion.</error>');
                return Command::FAILURE;
            }

            // Step 2: Set up Spotify API client
            $output->writeln('Initializing Spotify API...');
            $spotifyApi = $this->spotifyService->getSpotifyAPI();

            // Step 3: Find recent tracks for the artists
            $output->writeln('Fetching recent tracks for artists...');
            $singleTrackUris = $this->getRecentArtistItems($spotifyApi, $artistUris, 'single', $output);
            $albumTrackUris = $this->getRecentArtistItems($spotifyApi, $artistUris, 'album', $output);
            $mergedTrackUris = array_merge($singleTrackUris, $albumTrackUris);
            $trackUris = $this->filterUnwantedSongs($spotifyApi, $this->filterDuplicateTracks($spotifyApi, $mergedTrackUris));

            if (empty($trackUris)) {
                $output->writeln('<error>No recent tracks found for the artists.</error>');
                return Command::FAILURE;
            }

            // Step 4: Create or update playlist
            $playlistName = (new \DateTime('first day of last month'))->format('F Y');
            $output->writeln("Creating or updating playlist: $playlistName...");
            $playlistId = $this->spotifyService->createOrUpdatePlaylist($playlistName, $trackUris);

            $output->writeln("<info>Playlist updated successfully! Playlist ID: $playlistId</info>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    /**
     * Fetches recent tracks for a list of artists from Spotify, filtering out duplicate songs by the same artist.
     *
     * @param SpotifyWebAPI $spotifyApi The Spotify API client.
     * @param array $artistUris An array of artist URIs.
     * @param string $searchType The type of search to perform ('single' or 'album').
     * @return array An array of unique track URIs.
     */
    private function getRecentArtistItems(SpotifyWebAPIWrapper $spotifyApi, array $artistUris, string $searchType, OutputInterface $output): array
    {
        $recentTracks = [];

        foreach ($artistUris as $artistUri) {
            try {
                $artistId = str_replace('spotify:artist:', '', $artistUri->toString());
                $offset = 0;
                $limit = 20;
                $allAlbums = [];

                // Fetch all albums using pagination
                $maxRetries = 3;
                $retryCount = 0;
                
                do {
                    try {
                        $albums = $spotifyApi->getArtistAlbums($artistId, [
                            'include_groups' => $searchType, // e.g., 'album,single'
                            'limit' => $limit,
                            'offset' => $offset,
                        ]);

                        $allAlbums = array_merge($allAlbums, $albums->items);
                        $offset += $limit;
                        $retryCount = 0; // Reset retry count on success

                        // Introduce a small delay between requests to ease rate limits
                        usleep(1000_000); // 1000 milliseconds (1 seconds)
                    } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
                        if ($e->getCode() === 429) { // Rate limit error
                            $retryCount++;
                            if ($retryCount > $maxRetries) {
                                $output->writeln("Maximum retries ({$maxRetries}) exceeded for artist {$artistId}. Skipping...");
                                break;
                            }
                            
                            $retryAfter = $spotifyApi->getLastResponseHeaders()['Retry-After'] ?? 1;
                            $backoffTime = $retryAfter * $retryCount; // Exponential backoff
                            $output->writeln("Rate limit exceeded (attempt {$retryCount}/{$maxRetries}). Retrying after {$backoffTime} seconds...");
                            sleep($backoffTime);
                            continue; // Retry the current request
                        } else {
                            // Non-rate-limit error, re-throw
                            throw $e;
                        }
                    }
                } while (count($albums->items) > 0); // Continue until no more albums are returned

                // Filter albums by release date
                $firstDayOfLastMonth = (new \DateTime('first day of last month'))->setTime(0, 0);
                $lastDayOfLastMonth = (new \DateTime('last day of last month'))->setTime(23, 59, 59);

                $filteredAlbums = array_filter($allAlbums, function ($album) use ($firstDayOfLastMonth, $lastDayOfLastMonth) {
                    $releaseDate = new \DateTime($album->release_date);
                    return $releaseDate >= $firstDayOfLastMonth && $releaseDate <= $lastDayOfLastMonth;
                });

                // Collect track IDs for batching
                $trackIds = [];
                foreach ($filteredAlbums as $album) {
                    $output->writeln("Processing album: {$album->name}...");
                    
                    // Add rate limiting protection for album tracks
                    $albumTracksRetries = 0;
                    $maxAlbumTracksRetries = 3;
                    
                    while ($albumTracksRetries <= $maxAlbumTracksRetries) {
                        try {
                            $albumTracks = $spotifyApi->getAlbumTracks($album->id, ['limit' => 50]);
                            foreach ($albumTracks->items as $track) {
                                $trackIds[] = $track->id;
                            }
                            break; // Success, exit retry loop
                        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
                            if ($e->getCode() === 429) { // Rate limit error
                                $albumTracksRetries++;
                                if ($albumTracksRetries > $maxAlbumTracksRetries) {
                                    $output->writeln("Maximum retries exceeded for album tracks. Skipping album: {$album->name}");
                                    break;
                                }
                                
                                $retryAfter = $spotifyApi->getLastResponseHeaders()['Retry-After'] ?? 1;
                                $backoffTime = $retryAfter * $albumTracksRetries;
                                $output->writeln("Rate limit exceeded for album tracks (attempt {$albumTracksRetries}/{$maxAlbumTracksRetries}). Retrying after {$backoffTime} seconds...");
                                sleep($backoffTime);
                            } else {
                                // Non-rate-limit error, re-throw
                                throw $e;
                            }
                        }
                    }
                    
                    // Small delay between album processing
                    usleep(500_000); // 500ms delay
                }

                // Fetch track details in batches of 50
                foreach (array_chunk($trackIds, 50) as $trackBatch) {
                    $trackDetailsRetries = 0;
                    $maxTrackDetailsRetries = 3;
                    
                    while ($trackDetailsRetries <= $maxTrackDetailsRetries) {
                        try {
                            $tracks = $spotifyApi->getTracks($trackBatch);
                            foreach ($tracks->tracks as $track) {
                                $recentTracks[] = $track->uri;
                            }
                            break; // Success, exit retry loop
                        } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
                            if ($e->getCode() === 429) { // Rate limit error
                                $trackDetailsRetries++;
                                if ($trackDetailsRetries > $maxTrackDetailsRetries) {
                                    $output->writeln("Maximum retries exceeded for track details. Skipping batch...");
                                    break;
                                }
                                
                                $retryAfter = $spotifyApi->getLastResponseHeaders()['Retry-After'] ?? 1;
                                $backoffTime = $retryAfter * $trackDetailsRetries;
                                $output->writeln("Rate limit exceeded for track details (attempt {$trackDetailsRetries}/{$maxTrackDetailsRetries}). Retrying after {$backoffTime} seconds...");
                                sleep($backoffTime);
                            } else {
                                // Non-rate-limit error, re-throw
                                throw $e;
                            }
                        }
                    }
                    
                    // Small delay between batch processing
                    usleep(500_000); // 500ms delay
                }
            } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
                if ($e->getCode() === 401) { // Token expired
                    $output->writeln("Access token expired. Refreshing token...");
                    $this->spotifyService->refreshAccessToken();
                    $spotifyApi->setAccessToken($this->spotifyService->getSession()->getAccessToken());
                    $output->writeln("Token refreshed. Retrying...");
                    continue; // Retry the current artist
                } else {
                    $output->writeln("Error fetching data for artist: " . $artistUri->toString() . ". Skipping...");
                }
            } catch (\Exception $e) {
                $output->writeln("Unexpected error: " . $e->getMessage());
            }
        }

        return array_unique($recentTracks); // Avoid duplicate track URIs
    }

    private function filterDuplicateTracks(SpotifyWebAPIWrapper $spotifyApi, array $mergedTrackUris): array
    {
        $trackUris = [];
        $trackCheck = [];

        foreach ($mergedTrackUris as $trackUri) {
            $trackDetailsRetries = 0;
            $maxTrackDetailsRetries = 3;
            
            while ($trackDetailsRetries <= $maxTrackDetailsRetries) {
                try {
                    $track = $spotifyApi->getTrack($trackUri);
                    $trackName = $track->name;
                    $trackArtistId = $track->artists[0]->id;

                    if (!isset($trackCheck[$trackArtistId][$trackName])) {
                        $trackUris[] = $trackUri;
                        $trackCheck[$trackArtistId][$trackName] = true;
                    }
                    break; // Success, exit retry loop
                } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
                    if ($e->getCode() === 429) { // Rate limit error
                        $trackDetailsRetries++;
                        if ($trackDetailsRetries > $maxTrackDetailsRetries) {
                            // Skip this track if we can't fetch its details
                            break;
                        }
                        
                        $retryAfter = $spotifyApi->getLastResponseHeaders()['Retry-After'] ?? 1;
                        $backoffTime = $retryAfter * $trackDetailsRetries;
                        sleep($backoffTime);
                    } else {
                        // Non-rate-limit error, skip this track
                        break;
                    }
                }
            }
            
            // Small delay between track processing
            usleep(100_000); // 100ms delay
        }
        return $trackUris;
    }

    /**
     * Filter out songs that have
     * "instrumental", "acoustic", "live", "karaoke", "remix", "cover" ," remaster",
     * "edition", "version", "session", "demo", "mix", "track", "original", "edit", "extended"
     * in the name and the album name
     * @param SpotifyWebAPI $spotifyApi
     * @param array $trackUris
     * @return array
     */
    private function filterUnwantedSongs(SpotifyWebAPIWrapper $spotifyApi, array $trackUris): array
    {
        // Filter out tracks with blacklisted words in their names
        return array_filter($trackUris, function ($trackUri) use ($spotifyApi) {
            $trackDetailsRetries = 0;
            $maxTrackDetailsRetries = 3;
            
            while ($trackDetailsRetries <= $maxTrackDetailsRetries) {
                try {
                    $track = $spotifyApi->getTrack($trackUri);
                    foreach (self::$blacklistedWords as $word) {
                        if (stripos($track->name, $word) !== false) {
                            return false;
                        }
                    }
                    return true; // Track passed filtering
                } catch (\SpotifyWebAPI\SpotifyWebAPIException $e) {
                    if ($e->getCode() === 429) { // Rate limit error
                        $trackDetailsRetries++;
                        if ($trackDetailsRetries > $maxTrackDetailsRetries) {
                            // If we can't fetch track details, include it to be safe
                            return true;
                        }
                        
                        $retryAfter = $spotifyApi->getLastResponseHeaders()['Retry-After'] ?? 1;
                        $backoffTime = $retryAfter * $trackDetailsRetries;
                        sleep($backoffTime);
                    } else {
                        // Non-rate-limit error, include track to be safe
                        return true;
                    }
                }
            }
            
            // If we get here, include the track to be safe
            return true;
        });
    }
}
